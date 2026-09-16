<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\BackupHistory;
use App\Models\BackupSetting;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BackupAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function makeAdmin(): User
    {
        return User::create([
            'name' => 'Admin', 'username' => 'admin',
            'password' => Hash::make('pw'),
            'role' => UserRole::ADMINISTRATOR, 'is_active' => true,
        ]);
    }

    private function makeIdStaff(): User
    {
        return User::create([
            'name' => 'Staff', 'username' => 'idstaff',
            'password' => Hash::make('pw'),
            'role' => UserRole::ID_STAFF, 'is_active' => true,
        ]);
    }

    private function makeRegularUser(): User
    {
        return User::create([
            'name' => 'User', 'username' => 'regularuser',
            'password' => Hash::make('pw'),
            'role' => UserRole::USER, 'is_active' => true,
        ]);
    }

    private function makeHistory(array $overrides = []): BackupHistory
    {
        return BackupHistory::create(array_merge([
            'type'       => 'manual',
            'filename'   => 'IDTracker_Backup_2026-01-01_020000.sql',
            'path'       => storage_path('app/backups/IDTracker_Backup_2026-01-01_020000.sql'),
            'size'       => 1024,
            'status'     => 'success',
            'started_at' => now(),
        ], $overrides));
    }

    // ── Admin is allowed ───────────────────────────────────────────────────────

    #[Test]
    public function admin_can_access_backup_settings_page(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->get(route('settings.backup.index'))->assertOk();
    }

    #[Test]
    public function admin_can_save_backup_settings(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->put(route('settings.backup.update'), [
            'enabled'                => '0',
            'frequency'              => 'daily',
            'backup_time'            => '03:00',
            'backup_path'            => storage_path('app/backups'),
            'include_uploaded_files' => '0',
            'retention_days'         => '14',
        ]);

        $response->assertRedirect(route('settings.backup.index'));
        $this->assertEquals(14, BackupSetting::getInstance()->retention_days);
    }

    #[Test]
    public function admin_can_test_backup_path(): void
    {
        $admin = $this->makeAdmin();
        BackupSetting::getInstance()->update(['backup_path' => sys_get_temp_dir()]);

        $this->actingAs($admin)
            ->post(route('settings.backup.test'))
            ->assertRedirect();
    }

    #[Test]
    public function admin_can_delete_backup_history_record(): void
    {
        $admin   = $this->makeAdmin();
        $history = $this->makeHistory();

        // Mock BackupService to avoid real file operations
        $mock = $this->mock(BackupService::class);
        $mock->shouldReceive('isRunning')->andReturn(false);
        $mock->shouldReceive('deleteBackup')->once()->with(\Mockery::on(fn($h) => $h->id === $history->id));

        $this->actingAs($admin)
            ->delete(route('settings.backup.destroy', $history))
            ->assertRedirect();
    }

    // ── ID Staff receives 403 on all backup endpoints ─────────────────────────

    #[Test]
    public function id_staff_cannot_access_backup_settings_page(): void
    {
        $staff = $this->makeIdStaff();
        $this->actingAs($staff)->get(route('settings.backup.index'))->assertStatus(403);
    }

    #[Test]
    public function id_staff_cannot_save_backup_settings(): void
    {
        $staff = $this->makeIdStaff();
        $this->actingAs($staff)->put(route('settings.backup.update'), [
            'enabled'                => '1',
            'frequency'              => 'daily',
            'backup_time'            => '02:00',
            'backup_path'            => 'C:\\Backups',
            'include_uploaded_files' => '0',
            'retention_days'         => '30',
        ])->assertStatus(403);
    }

    #[Test]
    public function id_staff_cannot_run_manual_backup(): void
    {
        $staff = $this->makeIdStaff();
        $this->actingAs($staff)->post(route('settings.backup.run'))->assertStatus(403);
    }

    #[Test]
    public function id_staff_cannot_test_backup_path(): void
    {
        $staff = $this->makeIdStaff();
        $this->actingAs($staff)->post(route('settings.backup.test'))->assertStatus(403);
    }

    #[Test]
    public function id_staff_cannot_download_backup(): void
    {
        $staff   = $this->makeIdStaff();
        $history = $this->makeHistory();
        $this->actingAs($staff)
            ->get(route('settings.backup.download', $history))
            ->assertStatus(403);
    }

    #[Test]
    public function id_staff_cannot_delete_backup(): void
    {
        $staff   = $this->makeIdStaff();
        $history = $this->makeHistory();
        $this->actingAs($staff)
            ->delete(route('settings.backup.destroy', $history))
            ->assertStatus(403);
    }

    // ── Regular user receives 403 on all backup endpoints ─────────────────────

    #[Test]
    public function regular_user_cannot_access_backup_settings_page(): void
    {
        $user = $this->makeRegularUser();
        $this->actingAs($user)->get(route('settings.backup.index'))->assertStatus(403);
    }

    #[Test]
    public function regular_user_cannot_run_manual_backup(): void
    {
        $user = $this->makeRegularUser();
        $this->actingAs($user)->post(route('settings.backup.run'))->assertStatus(403);
    }

    #[Test]
    public function regular_user_cannot_download_backup(): void
    {
        $user    = $this->makeRegularUser();
        $history = $this->makeHistory();
        $this->actingAs($user)
            ->get(route('settings.backup.download', $history))
            ->assertStatus(403);
    }

    #[Test]
    public function regular_user_cannot_delete_backup(): void
    {
        $user    = $this->makeRegularUser();
        $history = $this->makeHistory();
        $this->actingAs($user)
            ->delete(route('settings.backup.destroy', $history))
            ->assertStatus(403);
    }

    // ── Unauthenticated redirects to login ────────────────────────────────────

    #[Test]
    public function unauthenticated_request_redirects_to_login(): void
    {
        $this->get(route('settings.backup.index'))->assertRedirect(route('login'));
        $this->post(route('settings.backup.run'))->assertRedirect(route('login'));
    }

    // ── BackupService unit tests ───────────────────────────────────────────────

    #[Test]
    public function backup_service_reports_not_running_when_no_lock(): void
    {
        Cache::forget('backup_running');
        $service = app(BackupService::class);
        $this->assertFalse($service->isRunning());
    }

    #[Test]
    public function backup_service_reports_running_when_lock_exists(): void
    {
        Cache::put('backup_running', true, 60);
        $service = app(BackupService::class);
        $this->assertTrue($service->isRunning());
        Cache::forget('backup_running');
    }

    #[Test]
    public function backup_service_throws_when_already_running(): void
    {
        Cache::put('backup_running', true, 60);
        $service = app(BackupService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/in progress/i');

        try {
            $service->run('manual', null);
        } finally {
            Cache::forget('backup_running');
        }
    }

    #[Test]
    public function backup_settings_validation_rejects_invalid_frequency(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->put(route('settings.backup.update'), [
            'enabled'                => '0',
            'frequency'              => 'hourly', // invalid
            'backup_time'            => '02:00',
            'backup_path'            => storage_path('app'),
            'include_uploaded_files' => '0',
            'retention_days'         => '30',
        ])->assertSessionHasErrors('frequency');
    }

    #[Test]
    public function backup_settings_validation_rejects_monthly_day_above_28(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->put(route('settings.backup.update'), [
            'enabled'                => '0',
            'frequency'              => 'monthly',
            'monthly_day'            => 31, // invalid
            'backup_time'            => '02:00',
            'backup_path'            => storage_path('app'),
            'include_uploaded_files' => '0',
            'retention_days'         => '30',
        ])->assertSessionHasErrors('monthly_day');
    }

    #[Test]
    public function backup_settings_weekly_requires_weekly_day(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->put(route('settings.backup.update'), [
            'enabled'                => '0',
            'frequency'              => 'weekly',
            // weekly_day intentionally missing
            'backup_time'            => '02:00',
            'backup_path'            => storage_path('app'),
            'include_uploaded_files' => '0',
            'retention_days'         => '30',
        ])->assertSessionHasErrors('weekly_day');
    }

    #[Test]
    public function backup_service_test_configuration_returns_error_for_bad_path(): void
    {
        $settings = BackupSetting::getInstance();
        $settings->backup_path = base_path(); // inside app source — should be rejected

        $service = app(BackupService::class);
        $error   = $service->testConfiguration($settings);

        $this->assertNotNull($error);
        $this->assertStringContainsStringIgnoringCase('application source', $error);
    }

    #[Test]
    public function backup_service_test_configuration_passes_for_valid_path(): void
    {
        $settings = BackupSetting::getInstance();
        // Use the system temp directory — guaranteed external and writable
        $settings->backup_path = sys_get_temp_dir();

        $service = app(BackupService::class);
        $error   = $service->testConfiguration($settings);

        $this->assertNull($error);
    }
}
