<?php

namespace Tests\Feature;

use App\Enums\IdStatus;
use App\Enums\UserRole;
use App\Models\IdRecord;
use App\Models\IdStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IdStatusTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::create([
            'name'      => 'Admin',
            'username'  => 'admin',
            'password'  => Hash::make('password'),
            'role'      => UserRole::ADMINISTRATOR,
            'is_active' => true,
        ]);
    }

    private function makeUser(): User
    {
        return User::create([
            'name'      => 'Staff',
            'username'  => 'staff',
            'password'  => Hash::make('password'),
            'role'      => UserRole::USER,
            'is_active' => true,
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

    #[Test]
    public function admin_can_change_id_status(): void
    {
        $admin  = $this->makeAdmin();
        $record = $this->makeRecord();

        $response = $this->actingAs($admin)->patch(route('id-records.status.update', $record), [
            'status'  => IdStatus::FOR_PROCESSING->value,
            'remarks' => 'Submitted for processing',
        ]);

        $response->assertRedirect();
        $record->refresh();
        $this->assertEquals(IdStatus::FOR_PROCESSING->value, $record->status);
    }

    #[Test]
    public function status_history_is_created_on_status_change(): void
    {
        $admin  = $this->makeAdmin();
        $record = $this->makeRecord();

        $this->actingAs($admin)->patch(route('id-records.status.update', $record), [
            'status'  => IdStatus::FOR_PROCESSING->value,
            'remarks' => 'Test remark',
        ]);

        $this->assertDatabaseHas('id_status_histories', [
            'id_record_id' => $record->id,
            'old_status'   => IdStatus::PENDING->value,
            'new_status'   => IdStatus::FOR_PROCESSING->value,
            'changed_by'   => $admin->id,
            'remarks'      => 'Test remark',
        ]);
    }

    #[Test]
    public function history_records_old_and_new_status(): void
    {
        $admin  = $this->makeAdmin();
        $record = $this->makeRecord(['status' => IdStatus::READY->value]);

        $this->actingAs($admin)->patch(route('id-records.status.update', $record), [
            'status' => IdStatus::RELEASED->value,
        ]);

        $history = IdStatusHistory::where('id_record_id', $record->id)->first();
        $this->assertEquals(IdStatus::READY->value, $history->old_status);
        $this->assertEquals(IdStatus::RELEASED->value, $history->new_status);
        $this->assertEquals($admin->id, $history->changed_by);
    }

    /**
     * CRITICAL: Regular user MUST receive 403 when attempting to change status.
     * The database status must remain unchanged.
     */
    #[Test]
    public function regular_user_cannot_change_status_and_receives_403(): void
    {
        $user   = $this->makeUser();
        $record = $this->makeRecord();

        $response = $this->actingAs($user)->patch(route('id-records.status.update', $record), [
            'status' => IdStatus::RELEASED->value,
        ]);

        $response->assertStatus(403);

        // Database must be unchanged
        $record->refresh();
        $this->assertEquals(IdStatus::PENDING->value, $record->status);

        // No history should be created
        $this->assertDatabaseMissing('id_status_histories', [
            'id_record_id' => $record->id,
        ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_change_status(): void
    {
        $record = $this->makeRecord();

        $response = $this->patch(route('id-records.status.update', $record), [
            'status' => IdStatus::RELEASED->value,
        ]);

        $response->assertRedirect(route('login'));
        $record->refresh();
        $this->assertEquals(IdStatus::PENDING->value, $record->status);
    }

    #[Test]
    public function invalid_status_value_is_rejected(): void
    {
        $admin  = $this->makeAdmin();
        $record = $this->makeRecord();

        $response = $this->actingAs($admin)->patch(route('id-records.status.update', $record), [
            'status' => 'INVALID_STATUS_VALUE',
        ]);

        $response->assertSessionHasErrors('status');
        $record->refresh();
        $this->assertEquals(IdStatus::PENDING->value, $record->status);
    }

    #[Test]
    public function admin_can_bulk_change_status(): void
    {
        $admin   = $this->makeAdmin();
        $record1 = $this->makeRecord(['id_number' => '001', 'status' => IdStatus::PENDING->value]);
        $record2 = IdRecord::create(['name' => 'Ana Reyes', 'id_number' => '002', 'status' => IdStatus::PENDING->value]);

        $response = $this->actingAs($admin)->post(route('id-records.status.bulk'), [
            'ids'     => [$record1->id, $record2->id],
            'status'  => IdStatus::READY->value,
            'remarks' => 'Bulk ready',
        ]);

        $response->assertRedirect();
        $this->assertEquals(IdStatus::READY->value, $record1->fresh()->status);
        $this->assertEquals(IdStatus::READY->value, $record2->fresh()->status);
        $this->assertEquals(2, IdStatusHistory::count());
    }

    #[Test]
    public function regular_user_cannot_bulk_change_status(): void
    {
        $user   = $this->makeUser();
        $record = $this->makeRecord();

        $response = $this->actingAs($user)->post(route('id-records.status.bulk'), [
            'ids'    => [$record->id],
            'status' => IdStatus::READY->value,
        ]);

        $response->assertStatus(403);
    }
}
