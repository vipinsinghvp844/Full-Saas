<?php

namespace Tests\Feature;

use App\Models\Member;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\Trainer;
use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('SQLite PDO driver is not available in this PHP runtime.');
        }

        parent::setUp();
    }

    public function test_member_check_in_duplicate_guard_filters_summary_and_checkout_work(): void
    {
        [$tenant, $admin, $member, $trainer] = $this->attendanceFixture();
        $headers = $this->authHeaders($admin);
        $today = now()->toDateString();

        $checkInResponse = $this
            ->withHeaders($headers)
            ->postJson('/api/gym/attendance/check-in', [
                'member_id' => $member->id,
                'source' => 'manual',
            ]);

        $checkInResponse
            ->assertCreated()
            ->assertJsonPath('data.member.id', $member->id)
            ->assertJsonPath('data.trainer.id', $trainer->id)
            ->assertJsonPath('data.status', 'present')
            ->assertJsonPath('data.source', 'manual');

        $attendanceId = $checkInResponse->json('data.id');

        $this
            ->withHeaders($headers)
            ->postJson('/api/gym/attendance/check-in', [
                'member_id' => $member->id,
                'source' => 'manual',
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'This member is already checked in today.');

        $this
            ->withHeaders($headers)
            ->getJson("/api/gym/attendance?date={$today}&trainer_id={$trainer->id}&member_id={$member->id}&status=present")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.member.name', 'Ava Member')
            ->assertJsonPath('data.0.trainer.name', 'Tara Trainer');

        $this
            ->withHeaders($headers)
            ->getJson("/api/gym/attendance/today?date={$today}")
            ->assertOk()
            ->assertJsonPath('data.total_check_ins', 1)
            ->assertJsonPath('data.active_members_today', 1)
            ->assertJsonPath('data.currently_inside', 1);

        $this
            ->withHeaders($headers)
            ->postJson("/api/gym/attendance/{$attendanceId}/check-out")
            ->assertOk()
            ->assertJsonPath('data.is_inside', false);

        $this
            ->withHeaders($headers)
            ->getJson("/api/gym/attendance/today?date={$today}")
            ->assertOk()
            ->assertJsonPath('data.currently_inside', 0);

        $this->assertDatabaseHas('attendance', [
            'tenant_id' => $tenant->id,
            'member_id' => $member->id,
            'trainer_id' => $trainer->id,
            'date' => $today,
            'status' => 'present',
            'source' => 'manual',
        ]);
    }

    public function test_check_in_rejects_members_from_another_tenant(): void
    {
        [, $admin] = $this->attendanceFixture();
        [$otherTenant, , $otherMember] = $this->attendanceFixture('other-gym', 'Other Gym', 'other-admin@example.com', 'other-member@example.com');

        $this
            ->withHeaders($this->authHeaders($admin))
            ->postJson('/api/gym/attendance/check-in', [
                'member_id' => $otherMember->id,
                'source' => 'manual',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('attendance', [
            'tenant_id' => $otherTenant->id,
            'member_id' => $otherMember->id,
        ]);
    }

    protected function attendanceFixture(
        string $tenantSlug = 'power-gym',
        string $tenantName = 'Power Gym',
        string $adminEmail = 'admin@example.com',
        string $memberEmail = 'member@example.com',
    ): array {
        $tenant = Tenant::query()->create([
            'name' => $tenantName,
            'slug' => $tenantSlug,
            'email' => "{$tenantSlug}@example.com",
            'status' => 'active',
        ]);

        $admin = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Gym Admin',
            'email' => $adminEmail,
            'password' => Hash::make('password'),
        ]);

        $role = Role::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Gym Admin',
            'guard_name' => 'web',
        ]);

        $admin->roles()->attach($role->id, ['tenant_id' => $tenant->id]);
        $admin->load('roles');

        $trainerUser = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Tara Trainer',
            'email' => "trainer-{$tenantSlug}@example.com",
            'password' => Hash::make('password'),
        ]);

        $trainer = Trainer::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $trainerUser->id,
            'specialization' => 'Strength',
            'status' => 'active',
        ]);

        $memberUser = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Ava Member',
            'email' => $memberEmail,
            'password' => Hash::make('password'),
        ]);

        $member = Member::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $memberUser->id,
            'assigned_trainer_id' => $trainer->id,
            'phone' => '9999999999',
            'joining_date' => now()->subMonth()->toDateString(),
            'status' => 'active',
        ]);

        return [$tenant, $admin, $member, $trainer];
    }

    protected function authHeaders(User $user): array
    {
        return [
            'Authorization' => 'Bearer ' . JwtService::generateAccessToken($user->load('roles')),
            'Accept' => 'application/json',
        ];
    }
}
