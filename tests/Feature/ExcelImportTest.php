<?php

namespace Tests\Feature;

use App\Enums\IdStatus;
use App\Enums\UserRole;
use App\Models\IdRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ExcelImportTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Admin', 'username' => 'admin',
            'password' => Hash::make('password'),
            'role' => UserRole::ADMINISTRATOR, 'is_active' => true,
        ]);
    }

    private function makeIdStaff(): User
    {
        return User::create([
            'name' => 'Staff', 'username' => 'idstaff',
            'password' => Hash::make('password'),
            'role' => UserRole::ID_STAFF, 'is_active' => true,
        ]);
    }

    private function validHeaders(): array
    {
        return ['NAME', 'POS', 'IDNO', 'DATEH', 'BDATE', 'ECON', 'IMG', 'SIGN', 'EMPLOYMENT TYPE'];
    }

    /**
     * Build an .xlsx file from explicit rows.
     * $rows: array of arrays, each matching the header columns.
     */
    private function makeExcelFile(array $headers, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$headers], null, 'A1');
        if (! empty($rows)) {
            $sheet->fromArray($rows, null, 'A2');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'test_excel_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmp);
        return new UploadedFile($tmp, 'test.xlsx', null, null, true);
    }

    /**
     * Build a file with $dataRows meaningful rows followed by $blankCount
     * completely empty rows — simulating a real-world file with formatting
     * applied to hundreds of trailing rows.
     */
    private function makeExcelWithTrailingBlanks(
        array $dataRows,
        int   $blankCount
    ): UploadedFile {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([$this->validHeaders()], null, 'A1');

        foreach ($dataRows as $i => $row) {
            $sheet->fromArray([$row], null, 'A' . ($i + 2));
        }

        // Write explicitly empty rows to simulate formatted-but-blank cells.
        // Must have 9 empty columns (matching the new header count) so the
        // empty-row filter correctly identifies them as blank.
        $emptyRow = ['', '', '', '', '', '', '', '', ''];
        $startRow = count($dataRows) + 2;
        for ($r = 0; $r < $blankCount; $r++) {
            $sheet->fromArray([$emptyRow], null, 'A' . ($startRow + $r));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'test_blank_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmp);
        return new UploadedFile($tmp, 'test_with_blanks.xlsx', null, null, true);
    }

    // ── Existing tests (unchanged) ─────────────────────────────────────────────

    #[Test]
    public function valid_excel_template_imports_successfully(): void
    {
        $admin = $this->makeAdmin();
        Storage::fake('local');

        $file = $this->makeExcelFile(
            $this->validHeaders(),
            [['Ciara Maricar M. Tan', 'Admin Assistant', '200473', '02/28/2018', '11/03/1992', 'Carlos Tan: 09174468097', '', '', 'Employee']]
        );

        $log = app(\App\Services\IdImportService::class)->import($file, $admin, 'both');

        $this->assertEquals(1, $log->created_rows);
        $this->assertEquals(0, $log->failed_rows);
        $this->assertDatabaseHas('id_records', [
            'id_number' => '200473',
            'name'      => 'Ciara Maricar M. Tan',
            'status'    => IdStatus::PENDING->value,
        ]);
    }

    #[Test]
    public function invalid_headers_are_rejected(): void
    {
        $file  = $this->makeExcelFile(['WRONG', 'HEADERS', 'HERE'], [['v1', 'v2', 'v3']]);
        $error = app(\App\Services\IdImportService::class)->validateHeaders($file);

        $this->assertNotNull($error);
        $this->assertStringContainsString('Invalid Excel template', $error);
    }

    #[Test]
    public function existing_idno_does_not_create_duplicate(): void
    {
        $admin = $this->makeAdmin();
        IdRecord::create(['name' => 'Ciara Maricar M. Tan', 'id_number' => '200473', 'status' => IdStatus::FOR_PROCESSING->value]);

        $file = $this->makeExcelFile(
            $this->validHeaders(),
            [['Ciara Updated', 'Admin Assistant', '200473', '02/28/2018', '11/03/1992', 'Carlos Tan', '', '', 'Employee']]
        );

        $log = app(\App\Services\IdImportService::class)->import($file, $admin, 'both');

        $this->assertEquals(0, $log->created_rows);
        $this->assertEquals(1, $log->updated_rows);
        $this->assertEquals(1, IdRecord::where('id_number', '200473')->count());
    }

    #[Test]
    public function existing_status_is_preserved_during_import(): void
    {
        $admin = $this->makeAdmin();
        IdRecord::create(['name' => 'Ciara Maricar M. Tan', 'id_number' => '200473', 'status' => IdStatus::FOR_PROCESSING->value]);

        $file = $this->makeExcelFile(
            $this->validHeaders(),
            [['Ciara Maricar M. Tan', 'Admin Assistant', '200473', '02/28/2018', '11/03/1992', 'Carlos Tan', '', '', 'Employee']]
        );

        app(\App\Services\IdImportService::class)->import($file, $admin, 'both');

        $this->assertEquals(
            IdStatus::FOR_PROCESSING->value,
            IdRecord::where('id_number', '200473')->value('status')
        );
    }

    #[Test]
    public function export_contains_exactly_eight_required_headers(): void
    {
        $export = new \App\Exports\IdRecordExport(includeStatus: false);
        $this->assertEquals(['NAME', 'POS', 'IDNO', 'DATEH', 'BDATE', 'ECON', 'IMG', 'SIGN', 'EMPLOYMENT TYPE'], $export->headings());
    }

    #[Test]
    public function report_export_contains_status_column(): void
    {
        $export = new \App\Exports\IdRecordExport(includeStatus: true);
        $this->assertContains('STATUS', $export->headings());
        $this->assertCount(10, $export->headings());
    }

    #[Test]
    public function row_missing_idno_is_recorded_as_failed(): void
    {
        $admin = $this->makeAdmin();

        $file = $this->makeExcelFile(
            $this->validHeaders(),
            [['Juan Dela Cruz', 'Staff', '', '01/15/2020', '05/10/1990', '', '', '', 'Employee']]
        );

        $log = app(\App\Services\IdImportService::class)->import($file, $admin, 'both');

        $this->assertEquals(1, $log->failed_rows);
        $this->assertEquals(0, $log->created_rows);
    }

    // ── Regression: blank trailing rows ───────────────────────────────────────

    /**
     * The core regression test.
     * 3 meaningful data rows + 936 completely blank rows = should behave as 3 rows.
     */
    #[Test]
    public function blank_trailing_rows_are_completely_ignored(): void
    {
        $admin = $this->makeAdmin();

        $dataRows = [
            ['Alice Santos',    'Staff',    '300001', '', '', '', '', '', 'Employee'],
            ['Bob Reyes',       'Officer',  '300002', '', '', '', '', '', 'Employee'],
            ['Charlie Dela Cruz','Engineer','300003', '', '', '', '', '', 'Employee'],
        ];

        $file = $this->makeExcelWithTrailingBlanks($dataRows, blankCount: 936);
        $log  = app(\App\Services\IdImportService::class)->import($file, $admin, 'both');

        // Total must reflect only the 3 meaningful rows, not 939.
        $this->assertEquals(3, $log->total_rows,   'total_rows should be 3, not 939');
        $this->assertEquals(3, $log->created_rows, '3 records should be created');
        $this->assertEquals(0, $log->failed_rows,  'no errors should be generated');
        $this->assertEquals(0, $log->skipped_rows);

        // No error messages at all.
        $this->assertEmpty($log->errors);
    }

    /**
     * Preview must report the same counts as the actual import for the same file.
     */
    #[Test]
    public function preview_and_import_agree_on_row_counts(): void
    {
        $admin = $this->makeAdmin();

        $dataRows = [
            ['Alice Santos', 'Staff',   '300001', '', '', '', '', '', 'Employee'],
            ['Bob Reyes',    'Officer', '300002', '', '', '', '', '', 'Employee'],
            // one invalid row — missing IDNO
            ['Charlie Cruz', 'Eng',     '',       '', '', '', '', '', 'Employee'],
        ];

        $file1 = $this->makeExcelWithTrailingBlanks($dataRows, blankCount: 500);
        $file2 = $this->makeExcelWithTrailingBlanks($dataRows, blankCount: 500);

        $service = app(\App\Services\IdImportService::class);

        $preview = $service->preview($file1);
        $log     = $service->import($file2, $admin, 'both');

        // Both must agree on the total meaningful rows.
        $this->assertEquals($preview['total'],   $log->total_rows,   'total must match');
        // Preview invalid === import failed.
        $this->assertEquals($preview['invalid'], $log->failed_rows,  'invalid/failed must match');
        // Preview new === import created.
        $this->assertEquals($preview['new'],     $log->created_rows, 'new/created must match');
    }

    /**
     * Whitespace-only cells must be treated as empty.
     * A row of all spaces/tabs must be silently skipped.
     */
    #[Test]
    public function whitespace_only_rows_are_treated_as_empty(): void
    {
        $admin = $this->makeAdmin();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$this->validHeaders()], null, 'A1');
        // One valid row
        $sheet->fromArray([['Real Employee', 'Staff', '400001', '', '', '', '', '', 'Employee']], null, 'A2');
        // One row of all-whitespace cells
        $sheet->fromArray([['   ', '  ', '   ', ' ', '  ', '   ', '  ', '  ']], null, 'A3');
        $tmp = tempnam(sys_get_temp_dir(), 'ws_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmp);
        $file = new UploadedFile($tmp, 'test.xlsx', null, null, true);

        $log = app(\App\Services\IdImportService::class)->import($file, $admin, 'both');

        $this->assertEquals(1, $log->total_rows,   'whitespace row must not count');
        $this->assertEquals(1, $log->created_rows);
        $this->assertEquals(0, $log->failed_rows);
    }

    // ── Regression: partial rows still produce errors ─────────────────────────

    /**
     * A row with NAME but no IDNO is NOT empty — it must fail with an error.
     */
    #[Test]
    public function partial_row_with_name_but_no_idno_fails_with_error(): void
    {
        $admin = $this->makeAdmin();

        $file = $this->makeExcelFile(
            $this->validHeaders(),
            [['Juan Dela Cruz', 'Staff', '', '', '', '', '', '', 'Employee']]
        );

        $log = app(\App\Services\IdImportService::class)->import($file, $admin, 'both');

        $this->assertEquals(1, $log->total_rows,  'partial row must be counted');
        $this->assertEquals(1, $log->failed_rows, 'partial row must fail');
        $this->assertEquals(0, $log->created_rows);
        $this->assertNotEmpty($log->errors);
        $this->assertStringContainsString('IDNO is required', $log->errors[0]);
    }

    /**
     * A row with IDNO but no NAME is NOT empty — it must fail with an error.
     */
    #[Test]
    public function partial_row_with_idno_but_no_name_fails_with_error(): void
    {
        $admin = $this->makeAdmin();

        $file = $this->makeExcelFile(
            $this->validHeaders(),
            [['', 'Staff', '500001', '', '', '', '', '', 'Employee']]
        );

        $log = app(\App\Services\IdImportService::class)->import($file, $admin, 'both');

        $this->assertEquals(1, $log->total_rows);
        $this->assertEquals(1, $log->failed_rows);
        $this->assertEquals(0, $log->created_rows);
        $this->assertStringContainsString('NAME is required', $log->errors[0]);
    }

    // ── ID Staff import — blank rows must be ignored regardless of role ────────

    #[Test]
    public function id_staff_import_ignores_blank_trailing_rows(): void
    {
        $staff = $this->makeIdStaff();

        $file = $this->makeExcelWithTrailingBlanks(
            [['Staff Employee', 'Clerk', '600001', '', '', '', '', '', 'Employee']],
            blankCount: 200
        );

        $log = app(\App\Services\IdImportService::class)->import($file, $staff, 'both');

        $this->assertEquals(1, $log->total_rows);
        $this->assertEquals(1, $log->created_rows);
        $this->assertEquals(0, $log->failed_rows);
        $this->assertEmpty($log->errors);
    }

    /**
     * ID Staff importing an existing IDNO should count as skipped, NOT failed.
     */
    #[Test]
    public function id_staff_skipped_existing_idno_counts_as_skipped_not_failed(): void
    {
        $staff = $this->makeIdStaff();
        IdRecord::create(['name' => 'Existing Employee', 'id_number' => '700001', 'status' => IdStatus::READY->value]);

        $file = $this->makeExcelFile(
            $this->validHeaders(),
            [['Existing Employee', 'Staff', '700001', '', '', '', '', '', 'Employee']]
        );

        $log = app(\App\Services\IdImportService::class)->import($file, $staff, 'both');

        $this->assertEquals(1, $log->total_rows);
        $this->assertEquals(1, $log->skipped_rows);  // skipped, not failed
        $this->assertEquals(0, $log->failed_rows);
        $this->assertEquals(0, $log->created_rows);

        // Status must be untouched
        $this->assertEquals(IdStatus::READY->value, IdRecord::where('id_number', '700001')->value('status'));
    }

    // ── Preview is consistent with import ─────────────────────────────────────

    #[Test]
    public function preview_correctly_ignores_blank_trailing_rows(): void
    {
        $this->makeAdmin(); // needed for IdRecord::where() in preview

        $file = $this->makeExcelWithTrailingBlanks(
            [
                ['Alice',   'Staff', '800001', '', '', '', '', '', 'Employee'],
                ['Bob',     'Mgr',   '800002', '', '', '', '', '', 'Employee'],
                ['',        '',      '',       '', '', '', '', '', ''], // all blank → filtered out by empty-row check
            ],
            blankCount: 400
        );

        $preview = app(\App\Services\IdImportService::class)->preview($file);

        // Only 2 meaningful rows (3rd is blank → filtered out, not counted as invalid)
        $this->assertEquals(2, $preview['total']);
        $this->assertEquals(2, $preview['new']);
        $this->assertEquals(0, $preview['invalid']);
    }
}


