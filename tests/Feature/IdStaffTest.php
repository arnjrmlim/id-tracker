<?php

namespace Tests\Feature;

use App\Enums\IdStatus;
use App\Enums\UserRole;
use App\Models\IdRecord;
use App\Models\IdStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IdStaffTest extends TestCase
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
            'name' => 'ID Staff', 'username' => 'idstaff',
            'password' => Hash::make('password'),
            'role' => UserRole::ID_STAFF, 'is_active' => true,
        ]);
    }

    private function makeRegularUser(): User
    {
        return User::create([
            'name' => 'User', 'username' => 'regularuser',
            'password' => Hash::make('password'),
            'role' => UserRole::USER, 'is_active' => true,
        ]);
    }

    private function makeRecord(array $overrides = []): IdRecord
    {
        return IdRecord::create(array_merge([
            'name'      => 'Ciara Maricar M. Tan',
            'id_number' => '200473',
            'status'    => IdStatus::PENDING->value,
        ], $overrides));
    }

    private function makeExcel(array $headers, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([$headers], null, 'A1');
        if (! empty($rows)) {
            $sheet->fromArray($rows, null, 'A2');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'test_') . '.xlsx';
        (new Xlsx($spreadsheet))->save($tmp);
        return new UploadedFile($tmp, 'test.xlsx', null, null, true);
    }

    private function validHeaders(): array
    {
        return ['NAME', 'POS', 'IDNO', 'DATEH', 'BDATE', 'ECON', 'IMG', 'SIGN', 'EMPLOYMENT TYPE'];
    }

    // ── Role helpers ───────────────────────────────────────────────────────────

    #[Test]
    public function id_staff_role_helpers_work_correctly(): void
    {
        $staff = $this->makeIdStaff();
        $this->assertTrue($staff->isIdStaff());
        $this->assertFalse($staff->isAdmin());
        $this->assertFalse($staff->isRegularUser());
        $this->assertTrue($staff->canCreate());
    }

    // ── Authentication ─────────────────────────────────────────────────────────

    #[Test]
    public function id_staff_can_login(): void
    {
        $staff = $this->makeIdStaff();
        $response = $this->post(route('login.post'), [
            'username' => 'idstaff', 'password' => 'password',
        ]);
        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($staff);
    }

    // ── Dashboard ──────────────────────────────────────────────────────────────

    #[Test]
    public function id_staff_can_view_dashboard(): void
    {
        $staff = $this->makeIdStaff();
        $this->actingAs($staff)->get(route('dashboard'))->assertOk();
    }

    // ── View records ───────────────────────────────────────────────────────────

    #[Test]
    public function id_staff_can_view_id_records_list(): void
    {
        $staff = $this->makeIdStaff();
        $this->makeRecord();
        $this->actingAs($staff)->get(route('id-records.index'))->assertOk();
    }

    #[Test]
    public function id_staff_can_view_id_record_detail(): void
    {
        $staff  = $this->makeIdStaff();
        $record = $this->makeRecord();
        $this->actingAs($staff)->get(route('id-records.show', $record))->assertOk();
    }

    #[Test]
    public function id_staff_can_view_status_history(): void
    {
        $staff = $this->makeIdStaff();
        $this->actingAs($staff)->get(route('history.index'))->assertOk();
    }

    // ── Create ─────────────────────────────────────────────────────────────────

    #[Test]
    public function id_staff_can_access_create_form(): void
    {
        $staff = $this->makeIdStaff();
        $this->actingAs($staff)->get(route('id-records.create'))->assertOk();
    }

    #[Test]
    public function id_staff_can_create_new_id_record(): void
    {
        $staff = $this->makeIdStaff();
        $response = $this->actingAs($staff)->post(route('id-records.store'), [
            'name'      => 'New Employee',
            'id_number' => '300001',
            'position'  => 'Staff',
        ]);
        $response->assertRedirect(route('id-records.index'));
        $this->assertDatabaseHas('id_records', [
            'id_number' => '300001',
            'status'    => IdStatus::PENDING->value, // must always be PENDING
        ]);
    }

    #[Test]
    public function id_staff_cannot_set_status_on_create_via_manipulated_form(): void
    {
        $staff = $this->makeIdStaff();
        // Attempt to inject a non-PENDING status via POST body
        $this->actingAs($staff)->post(route('id-records.store'), [
            'name'      => 'Injected Status Employee',
            'id_number' => '300002',
            'status'    => IdStatus::RELEASED->value, // malicious injection attempt
        ]);
        // Status must be PENDING regardless of what was submitted
        $this->assertEquals(
            IdStatus::PENDING->value,
            IdRecord::where('id_number', '300002')->value('status')
        );
    }

    // ── Edit / Delete (denied) ─────────────────────────────────────────────────

    #[Test]
    public function id_staff_cannot_access_edit_form(): void
    {
        $staff  = $this->makeIdStaff();
        $record = $this->makeRecord();
        $this->actingAs($staff)
            ->get(route('id-records.edit', $record))
            ->assertStatus(403);
    }

    #[Test]
    public function id_staff_cannot_update_existing_record(): void
    {
        $staff  = $this->makeIdStaff();
        $record = $this->makeRecord();
        $response = $this->actingAs($staff)->put(route('id-records.update', $record), [
            'name'      => 'Hacked Name',
            'id_number' => '200473',
        ]);
        $response->assertStatus(403);
        $record->refresh();
        $this->assertEquals('Ciara Maricar M. Tan', $record->name);
    }

    #[Test]
    public function id_staff_cannot_delete_record(): void
    {
        $staff  = $this->makeIdStaff();
        $record = $this->makeRecord();
        $this->actingAs($staff)
            ->delete(route('id-records.destroy', $record))
            ->assertStatus(403);
        $this->assertDatabaseHas('id_records', ['id' => $record->id]);
    }

    // ── Status (denied) ────────────────────────────────────────────────────────

    #[Test]
    public function id_staff_cannot_change_id_status(): void
    {
        $staff  = $this->makeIdStaff();
        $record = $this->makeRecord(['status' => IdStatus::PENDING->value]);
        $response = $this->actingAs($staff)
            ->patch(route('id-records.status.update', $record), [
                'status' => IdStatus::RELEASED->value,
            ]);
        $response->assertStatus(403);
        $record->refresh();
        $this->assertEquals(IdStatus::PENDING->value, $record->status);
        $this->assertDatabaseMissing('id_status_histories', ['id_record_id' => $record->id]);
    }

    #[Test]
    public function id_staff_cannot_bulk_change_status(): void
    {
        $staff  = $this->makeIdStaff();
        $record = $this->makeRecord();
        $this->actingAs($staff)
            ->post(route('id-records.status.bulk'), [
                'ids'    => [$record->id],
                'status' => IdStatus::READY->value,
            ])
            ->assertStatus(403);
    }

    // ── User Management (denied) ───────────────────────────────────────────────

    #[Test]
    public function id_staff_cannot_access_user_management(): void
    {
        $staff = $this->makeIdStaff();
        $this->actingAs($staff)->get(route('users.index'))->assertStatus(403);
    }

    #[Test]
    public function id_staff_cannot_create_user(): void
    {
        $staff = $this->makeIdStaff();
        $this->actingAs($staff)->get(route('users.create'))->assertStatus(403);
        $this->actingAs($staff)->post(route('users.store'), [
            'name'                  => 'Evil User',
            'username'              => 'eviluser',
            'password'              => 'Password@1',
            'password_confirmation' => 'Password@1',
            'role'                  => 'administrator',
        ])->assertStatus(403);
    }

    #[Test]
    public function id_staff_cannot_edit_user(): void
    {
        $staff  = $this->makeIdStaff();
        $target = $this->makeRegularUser();
        $this->actingAs($staff)->get(route('users.edit', $target))->assertStatus(403);
        $this->actingAs($staff)->put(route('users.update', $target), [
            'name'     => 'Hacked',
            'username' => 'hacked',
            'role'     => 'administrator',
        ])->assertStatus(403);
    }

    #[Test]
    public function id_staff_cannot_delete_user(): void
    {
        $staff  = $this->makeIdStaff();
        $target = $this->makeRegularUser();
        $this->actingAs($staff)
            ->delete(route('users.destroy', $target))
            ->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    #[Test]
    public function id_staff_cannot_activate_deactivate_user(): void
    {
        $staff  = $this->makeIdStaff();
        $target = $this->makeRegularUser();
        $this->actingAs($staff)->patch(route('users.deactivate', $target))->assertStatus(403);
        $this->actingAs($staff)->patch(route('users.activate', $target))->assertStatus(403);
    }

    #[Test]
    public function id_staff_cannot_reset_another_users_password(): void
    {
        $staff  = $this->makeIdStaff();
        $target = $this->makeRegularUser();
        $this->actingAs($staff)->patch(route('users.reset-password', $target), [
            'password'              => 'NewPass@1234',
            'password_confirmation' => 'NewPass@1234',
        ])->assertStatus(403);
    }

    // ── Import ─────────────────────────────────────────────────────────────────

    #[Test]
    public function id_staff_can_access_import_page(): void
    {
        $staff = $this->makeIdStaff();
        $this->actingAs($staff)->get(route('imports.index'))->assertOk();
    }

    #[Test]
    public function id_staff_import_creates_new_record(): void
    {
        $staff = $this->makeIdStaff();
        Storage::fake('local');
        $file = $this->makeExcel($this->validHeaders(), [
            ['New Employee', 'Staff', '900001', '01/01/2023', '01/01/1990', '', '', '', 'Employee'],
        ]);
        $log = app(\App\Services\IdImportService::class)->import($file, $staff, 'both');
        $this->assertEquals(1, $log->created_rows);
        $this->assertDatabaseHas('id_records', [
            'id_number' => '900001',
            'status'    => IdStatus::PENDING->value,
        ]);
    }

    #[Test]
    public function id_staff_import_skips_existing_record_and_does_not_modify_it(): void
    {
        $staff = $this->makeIdStaff();
        Storage::fake('local');
        // Create existing record with non-PENDING status
        IdRecord::create([
            'name'      => 'Ciara Maricar M. Tan',
            'id_number' => '200473',
            'status'    => IdStatus::READY->value,
        ]);
        $file = $this->makeExcel($this->validHeaders(), [
            ['Ciara Updated Name', 'Admin', '200473', '02/28/2018', '11/03/1992', '', '', '', 'Employee'],
        ]);
        $log = app(\App\Services\IdImportService::class)->import($file, $staff, 'both');
        // Must be skipped — not updated
        $this->assertEquals(0, $log->updated_rows);
        $this->assertEquals(1, $log->skipped_rows);
        // Name and status must be unchanged
        $record = IdRecord::where('id_number', '200473')->first();
        $this->assertEquals('Ciara Maricar M. Tan', $record->name);
        $this->assertEquals(IdStatus::READY->value, $record->status);
    }

    #[Test]
    public function id_staff_import_mode_is_forced_to_add_even_if_both_passed(): void
    {
        $staff = $this->makeIdStaff();
        Storage::fake('local');
        IdRecord::create([
            'name' => 'Original Name', 'id_number' => '200473',
            'status' => IdStatus::FOR_PROCESSING->value,
        ]);
        $file = $this->makeExcel($this->validHeaders(), [
            ['Updated Name', 'Staff', '200473', '', '', '', '', '', 'Employee'],
        ]);
        // Even if 'both' or 'update' is passed, service hard-forces 'add' for ID Staff
        app(\App\Services\IdImportService::class)->import($file, $staff, 'update');
        $this->assertEquals('Original Name', IdRecord::where('id_number', '200473')->value('name'));
    }

    // ── Export ─────────────────────────────────────────────────────────────────

    #[Test]
    public function id_staff_can_access_export_page(): void
    {
        $staff = $this->makeIdStaff();
        $this->actingAs($staff)->get(route('exports.index'))->assertOk();
    }

    // ── Regular user denied import/export ──────────────────────────────────────

    #[Test]
    public function regular_user_cannot_access_import(): void
    {
        $user = $this->makeRegularUser();
        $this->actingAs($user)->get(route('imports.index'))->assertStatus(403);
    }

    #[Test]
    public function regular_user_cannot_access_export(): void
    {
        $user = $this->makeRegularUser();
        $this->actingAs($user)->get(route('exports.index'))->assertStatus(403);
    }

    #[Test]
    public function regular_user_cannot_create_record(): void
    {
        $user = $this->makeRegularUser();
        $this->actingAs($user)->get(route('id-records.create'))->assertStatus(403);
        $this->actingAs($user)->post(route('id-records.store'), [
            'name' => 'Test', 'id_number' => '999999',
        ])->assertStatus(403);
    }

    // ── Admin import still works normally ─────────────────────────────────────

    #[Test]
    public function admin_import_updates_existing_without_changing_status(): void
    {
        $admin = $this->makeAdmin();
        Storage::fake('local');
        IdRecord::create([
            'name' => 'Original Name', 'id_number' => '200473',
            'status' => IdStatus::READY->value,
        ]);
        $file = $this->makeExcel($this->validHeaders(), [
            ['Updated Admin Name', 'Manager', '200473', '', '', '', '', '', 'Employee'],
        ]);
        $log = app(\App\Services\IdImportService::class)->import($file, $admin, 'both');
        $this->assertEquals(1, $log->updated_rows);
        $record = IdRecord::where('id_number', '200473')->first();
        $this->assertEquals('Updated Admin Name', $record->name);
        $this->assertEquals(IdStatus::READY->value, $record->status); // unchanged
    }
}


