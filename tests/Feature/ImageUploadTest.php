<?php

namespace Tests\Feature;

use App\Enums\IdStatus;
use App\Enums\UserRole;
use App\Models\IdRecord;
use App\Models\User;
use App\Services\ImageUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImageUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Admin', 'username' => 'admin',
            'password' => Hash::make('pw'), 'role' => UserRole::ADMINISTRATOR, 'is_active' => true,
        ]);
    }

    private function makeIdStaff(): User
    {
        return User::create([
            'name' => 'Staff', 'username' => 'idstaff',
            'password' => Hash::make('pw'), 'role' => UserRole::ID_STAFF, 'is_active' => true,
        ]);
    }

    private function makeRegularUser(): User
    {
        return User::create([
            'name' => 'User', 'username' => 'regularuser',
            'password' => Hash::make('pw'), 'role' => UserRole::USER, 'is_active' => true,
        ]);
    }

    private function makeRecord(array $overrides = []): IdRecord
    {
        return IdRecord::create(array_merge([
            'name' => 'Ciara Maricar M. Tan', 'id_number' => '200473',
            'status' => IdStatus::PENDING->value,
        ], $overrides));
    }

    /** Fake a valid PNG upload file. */
    private function fakeImage(string $name = 'photo.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 100, 100);
    }

    /** Base store payload using network source. */
    private function networkPayload(array $overrides = []): array
    {
        return array_merge([
            'name'             => 'Test Employee',
            'id_number'        => '999001',
            'image_source'     => 'network',
            'image_path'       => 'Z:\\IT_Files\\test\\image.png',
            'signature_source' => 'network',
            'signature_path'   => 'Z:\\IT_Files\\test\\signature.png',
        ], $overrides);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Scenario A — Network path ID image + network signature
    // ══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function scenario_a_admin_creates_record_with_network_paths(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('id-records.store'), $this->networkPayload());

        $response->assertRedirect(route('id-records.index'));

        $this->assertDatabaseHas('id_records', [
            'id_number'       => '999001',
            'image_source'    => 'network',
            'image_path'      => 'Z:\\IT_Files\\test\\image.png',
            'signature_source'=> 'network',
            'signature_path'  => 'Z:\\IT_Files\\test\\signature.png',
            'image_upload_path'     => null,
            'signature_upload_path' => null,
            'status'          => IdStatus::PENDING->value,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Scenario B — Upload both images
    // ══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function scenario_b_admin_creates_record_with_uploaded_images(): void
    {
        $admin   = $this->makeAdmin();
        $imgFile = $this->fakeImage('id_front.png');
        $sigFile = $this->fakeImage('signature.png');

        $response = $this->actingAs($admin)->post(route('id-records.store'), [
            'name'             => 'Upload Employee',
            'id_number'        => '999002',
            'image_source'     => 'upload',
            'image_file'       => $imgFile,
            'signature_source' => 'upload',
            'signature_file'   => $sigFile,
        ]);

        $response->assertRedirect(route('id-records.index'));

        $record = IdRecord::where('id_number', '999002')->firstOrFail();
        $this->assertEquals('upload', $record->image_source);
        $this->assertEquals('upload', $record->signature_source);
        $this->assertNotNull($record->image_upload_path);
        $this->assertNotNull($record->signature_upload_path);
        $this->assertNull($record->image_path);
        $this->assertNull($record->signature_path);

        // Files must exist in storage
        Storage::disk('public')->assertExists($record->image_upload_path);
        Storage::disk('public')->assertExists($record->signature_upload_path);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Scenario C — Mixed: network image + uploaded signature
    // ══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function scenario_c_mixed_network_image_and_uploaded_signature(): void
    {
        $admin   = $this->makeAdmin();
        $sigFile = $this->fakeImage('sig.png');

        $response = $this->actingAs($admin)->post(route('id-records.store'), [
            'name'             => 'Mixed Employee',
            'id_number'        => '999003',
            'image_source'     => 'network',
            'image_path'       => 'Z:\\net\\image.png',
            'signature_source' => 'upload',
            'signature_file'   => $sigFile,
        ]);

        $response->assertRedirect(route('id-records.index'));

        $record = IdRecord::where('id_number', '999003')->firstOrFail();
        $this->assertEquals('network', $record->image_source);
        $this->assertEquals('Z:\\net\\image.png', $record->image_path);
        $this->assertEquals('upload', $record->signature_source);
        $this->assertNotNull($record->signature_upload_path);
        Storage::disk('public')->assertExists($record->signature_upload_path);
    }

    #[Test]
    public function scenario_c_mixed_uploaded_image_and_network_signature(): void
    {
        $admin   = $this->makeAdmin();
        $imgFile = $this->fakeImage('img.png');

        $response = $this->actingAs($admin)->post(route('id-records.store'), [
            'name'             => 'Mixed Employee 2',
            'id_number'        => '999004',
            'image_source'     => 'upload',
            'image_file'       => $imgFile,
            'signature_source' => 'network',
            'signature_path'   => 'Z:\\net\\sig.png',
        ]);

        $response->assertRedirect(route('id-records.index'));

        $record = IdRecord::where('id_number', '999004')->firstOrFail();
        $this->assertEquals('upload', $record->image_source);
        $this->assertEquals('network', $record->signature_source);
        $this->assertEquals('Z:\\net\\sig.png', $record->signature_path);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Scenario D — Excel import
    // ══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function scenario_d_excel_import_sets_network_source_from_img_sign(): void
    {
        $admin = $this->makeAdmin();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['NAME', 'POS', 'IDNO', 'DATEH', 'BDATE', 'ECON', 'IMG', 'SIGN', 'EMPLOYMENT TYPE']], null, 'A1');
        $sheet->fromArray([['Excel Employee', 'Staff', '999005', '', '', '',
            'Z:\\net\\excel_img.png', 'Z:\\net\\excel_sig.png', 'Employee']], null, 'A2');
        $tmp = tempnam(sys_get_temp_dir(), 'exc_') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tmp);
        $file = new UploadedFile($tmp, 'test.xlsx', null, null, true);

        app(\App\Services\IdImportService::class)->import($file, $admin, 'both');

        $this->assertDatabaseHas('id_records', [
            'id_number'        => '999005',
            'image_source'     => 'network',
            'image_path'       => 'Z:\\net\\excel_img.png',
            'signature_source' => 'network',
            'signature_path'   => 'Z:\\net\\excel_sig.png',
        ]);
    }

    #[Test]
    public function scenario_d_excel_import_blank_img_sets_null_source(): void
    {
        $admin = $this->makeAdmin();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([['NAME', 'POS', 'IDNO', 'DATEH', 'BDATE', 'ECON', 'IMG', 'SIGN', 'EMPLOYMENT TYPE']], null, 'A1');
        $sheet->fromArray([['Blank Img Employee', '', '999006', '', '', '', '', '', 'Employee']], null, 'A2');
        $tmp = tempnam(sys_get_temp_dir(), 'exc_') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tmp);
        $file = new UploadedFile($tmp, 'test.xlsx', null, null, true);

        app(\App\Services\IdImportService::class)->import($file, $admin, 'both');

        $record = IdRecord::where('id_number', '999006')->firstOrFail();
        $this->assertNull($record->image_source);
        $this->assertNull($record->image_path);
        $this->assertNull($record->signature_source);
        $this->assertNull($record->signature_path);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Scenario 6 — Missing network path rejected
    // ══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function network_source_without_path_fails_validation(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('id-records.store'), [
            'name'         => 'No Path',
            'id_number'    => '999007',
            'image_source' => 'network',
            'image_path'   => '', // blank — must fail
        ]);

        $response->assertSessionHasErrors('image_path');
        $this->assertDatabaseMissing('id_records', ['id_number' => '999007']);
    }

    #[Test]
    public function upload_source_without_file_fails_validation(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('id-records.store'), [
            'name'         => 'No File',
            'id_number'    => '999008',
            'image_source' => 'upload',
            // no image_file submitted
        ]);

        $response->assertSessionHasErrors('image_file');
        $this->assertDatabaseMissing('id_records', ['id_number' => '999008']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Scenario 7 — Invalid file type rejected
    // ══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function invalid_file_type_is_rejected(): void
    {
        $admin = $this->makeAdmin();

        $badFile = UploadedFile::fake()->create('malware.php', 100, 'text/plain');

        $response = $this->actingAs($admin)->post(route('id-records.store'), [
            'name'         => 'Bad File',
            'id_number'    => '999009',
            'image_source' => 'upload',
            'image_file'   => $badFile,
        ]);

        $response->assertSessionHasErrors('image_file');
        $this->assertDatabaseMissing('id_records', ['id_number' => '999009']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Scenario 8 — File larger than 5 MB rejected
    // ══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function file_exceeding_5mb_is_rejected(): void
    {
        $admin = $this->makeAdmin();

        $bigFile = UploadedFile::fake()->create('big.png', 6000, 'image/png'); // 6 MB

        $response = $this->actingAs($admin)->post(route('id-records.store'), [
            'name'         => 'Big File',
            'id_number'    => '999010',
            'image_source' => 'upload',
            'image_file'   => $bigFile,
        ]);

        $response->assertSessionHasErrors('image_file');
        $this->assertDatabaseMissing('id_records', ['id_number' => '999010']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Scenario 10 — ID Staff creating with uploaded images
    // ══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function id_staff_can_create_record_with_uploaded_images(): void
    {
        $staff   = $this->makeIdStaff();
        $imgFile = $this->fakeImage('staff_id.png');
        $sigFile = $this->fakeImage('staff_sig.png');

        $response = $this->actingAs($staff)->post(route('id-records.store'), [
            'name'             => 'Staff Created Employee',
            'id_number'        => '999011',
            'image_source'     => 'upload',
            'image_file'       => $imgFile,
            'signature_source' => 'upload',
            'signature_file'   => $sigFile,
        ]);

        $response->assertRedirect(route('id-records.index'));

        $record = IdRecord::where('id_number', '999011')->firstOrFail();
        $this->assertEquals('upload', $record->image_source);
        $this->assertEquals(IdStatus::PENDING->value, $record->status);
        Storage::disk('public')->assertExists($record->image_upload_path);
        Storage::disk('public')->assertExists($record->signature_upload_path);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Scenario 11 — ID Staff cannot edit existing record
    // ══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function id_staff_cannot_edit_existing_record_with_upload(): void
    {
        $staff  = $this->makeIdStaff();
        $record = $this->makeRecord();

        $this->actingAs($staff)
             ->get(route('id-records.edit', $record))
             ->assertStatus(403);

        $this->actingAs($staff)
             ->put(route('id-records.update', $record), [
                 'name'         => 'Hacked',
                 'id_number'    => '200473',
                 'image_source' => 'upload',
                 'image_file'   => $this->fakeImage(),
             ])
             ->assertStatus(403);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Scenario 12 — ID Staff cannot replace existing image via edit
    // ══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function id_staff_cannot_replace_existing_image_via_edit(): void
    {
        $staff  = $this->makeIdStaff();
        $record = $this->makeRecord([
            'image_source'      => 'upload',
            'image_upload_path' => 'id-images/2026/09/200473_abc.png',
        ]);

        $this->actingAs($staff)
             ->put(route('id-records.update', $record), [
                 'name'         => 'Any',
                 'id_number'    => '200473',
                 'image_source' => 'upload',
                 'image_file'   => $this->fakeImage(),
             ])
             ->assertStatus(403);

        // Upload path must be unchanged
        $this->assertEquals('id-images/2026/09/200473_abc.png', $record->fresh()->image_upload_path);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Scenario 13 — Admin replacing uploaded image
    // ══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function admin_can_replace_uploaded_image_and_old_file_is_deleted(): void
    {
        $admin = $this->makeAdmin();

        // Put a fake file in storage
        Storage::disk('public')->put('id-images/2026/09/200473_old.png', 'fake-content');

        $record = $this->makeRecord([
            'image_source'      => 'upload',
            'image_upload_path' => 'id-images/2026/09/200473_old.png',
        ]);

        $newFile = $this->fakeImage('new_photo.png');

        $response = $this->actingAs($admin)->put(route('id-records.update', $record), [
            'name'         => $record->name,
            'id_number'    => $record->id_number,
            'image_source' => 'upload',
            'image_file'   => $newFile,
        ]);

        $response->assertRedirect(route('id-records.show', $record));

        $record->refresh();

        // Old file should be gone
        Storage::disk('public')->assertMissing('id-images/2026/09/200473_old.png');

        // New file should exist
        Storage::disk('public')->assertExists($record->image_upload_path);
        $this->assertNotEquals('id-images/2026/09/200473_old.png', $record->image_upload_path);
    }

    #[Test]
    public function admin_switching_from_upload_to_network_deletes_uploaded_file(): void
    {
        $admin = $this->makeAdmin();

        Storage::disk('public')->put('id-images/2026/09/200473_todelete.png', 'data');

        $record = $this->makeRecord([
            'image_source'      => 'upload',
            'image_upload_path' => 'id-images/2026/09/200473_todelete.png',
        ]);

        $response = $this->actingAs($admin)->put(route('id-records.update', $record), [
            'name'         => $record->name,
            'id_number'    => $record->id_number,
            'image_source' => 'network',
            'image_path'   => 'Z:\\new\\path.png',
        ]);

        $response->assertRedirect(route('id-records.show', $record));

        $record->refresh();
        $this->assertEquals('network', $record->image_source);
        $this->assertEquals('Z:\\new\\path.png', $record->image_path);
        $this->assertNull($record->image_upload_path);

        // Old uploaded file must be deleted
        Storage::disk('public')->assertMissing('id-images/2026/09/200473_todelete.png');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Scenario 14 — Regular user cannot upload or modify images
    // ══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function regular_user_cannot_create_record_with_image(): void
    {
        $user = $this->makeRegularUser();

        $this->actingAs($user)
             ->post(route('id-records.store'), [
                 'name'         => 'Attempt',
                 'id_number'    => '999099',
                 'image_source' => 'upload',
                 'image_file'   => $this->fakeImage(),
             ])
             ->assertStatus(403);

        $this->assertDatabaseMissing('id_records', ['id_number' => '999099']);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // Scenario 15 — Network files are NEVER deleted by Laravel
    // ══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function network_files_are_never_deleted_when_record_is_modified(): void
    {
        $admin  = $this->makeAdmin();
        $record = $this->makeRecord([
            'image_source' => 'network',
            'image_path'   => 'Z:\\IT_Files\\original.png',
        ]);

        // Update the record — change name only, keep image
        $this->actingAs($admin)->put(route('id-records.update', $record), [
            'name'         => 'Updated Name',
            'id_number'    => $record->id_number,
            'image_source' => 'network',
            'image_path'   => 'Z:\\IT_Files\\original.png',
        ]);

        // The path must remain exactly as-is — application never touches external files
        $this->assertEquals('Z:\\IT_Files\\original.png', $record->fresh()->image_path);

        // No storage files were created or deleted (nothing in Laravel-managed storage)
        Storage::disk('public')->assertMissing('Z:\\IT_Files\\original.png');
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ImageUploadService unit-level tests
    // ══════════════════════════════════════════════════════════════════════════

    #[Test]
    public function image_upload_service_stores_id_image_with_correct_path_pattern(): void
    {
        $service = app(ImageUploadService::class);
        $file    = $this->fakeImage('test.png');

        $path = $service->storeIdImage($file, '200473');

        $this->assertStringStartsWith('id-images/', $path);
        $this->assertStringContainsString('200473', $path);
        Storage::disk('public')->assertExists($path);
    }

    #[Test]
    public function image_upload_service_stores_signature_with_correct_path_pattern(): void
    {
        $service = app(ImageUploadService::class);
        $file    = $this->fakeImage('sig.png');

        $path = $service->storeSignature($file, '200473');

        $this->assertStringStartsWith('signatures/', $path);
        $this->assertStringContainsString('200473', $path);
        $this->assertStringContainsString('signature', $path);
        Storage::disk('public')->assertExists($path);
    }

    #[Test]
    public function image_upload_service_delete_removes_file_from_storage(): void
    {
        $service = app(ImageUploadService::class);
        Storage::disk('public')->put('id-images/test_delete.png', 'data');

        $service->deleteUploadedFile('id-images/test_delete.png');

        Storage::disk('public')->assertMissing('id-images/test_delete.png');
    }

    #[Test]
    public function image_upload_service_delete_does_not_throw_for_missing_file(): void
    {
        $service = app(ImageUploadService::class);
        // Should not throw even if the file doesn't exist
        $service->deleteUploadedFile('id-images/nonexistent.png');
        $this->assertTrue(true); // reached here = no exception
    }

    #[Test]
    public function image_upload_service_delete_does_nothing_for_null_path(): void
    {
        $service = app(ImageUploadService::class);
        $service->deleteUploadedFile(null);
        $this->assertTrue(true);
    }
}


