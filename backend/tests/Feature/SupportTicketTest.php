<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    protected User $gymAdmin;
    protected User $superAdmin;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('SQLite PDO driver is not available in this PHP runtime.');
        }

        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Power House Gym',
            'slug' => 'test-power-house-gym',
            'email' => 'test@powerhousegym.com',
            'phone' => '1234567890',
            'address' => 'Test Ave',
            'status' => 'active',
        ]);

        $this->gymAdmin = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Gym Admin Test',
            'email' => 'admin.test@powerhousegym.com',
            'password' => bcrypt('password'),
        ]);

        $gymAdminRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Gym Admin',
            'guard_name' => 'api',
        ]);

        $this->gymAdmin->roles()->sync([
            $gymAdminRole->id => ['tenant_id' => $this->tenant->id]
        ]);

        $this->superAdmin = User::create([
            'tenant_id' => null,
            'name' => 'Super Admin Test',
            'email' => 'superadmin.test@gym.com',
            'password' => bcrypt('password'),
        ]);

        $superAdminRole = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Super Admin',
            'guard_name' => 'api',
        ]);

        $this->superAdmin->roles()->sync([
            $superAdminRole->id => ['tenant_id' => $this->tenant->id]
        ]);
    }

    public function test_gym_admin_can_create_support_ticket()
    {
        $response = $this->actingAs($this->gymAdmin, 'api')
            ->postJson('/api/gym/support/tickets', [
                'subject' => 'Database Backup Issue',
                'description' => 'The backup failed with status 500 error today.',
                'priority' => 'high',
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.subject', 'Database Backup Issue');

        $this->assertDatabaseHas('support_tickets', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->gymAdmin->id,
            'subject' => 'Database Backup Issue',
            'priority' => 'high',
            'status' => 'open',
        ]);
    }

    public function test_gym_admin_can_upload_attachment()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('screenshot.png');

        $response = $this->actingAs($this->gymAdmin, 'api')
            ->postJson('/api/gym/support/upload', [
                'file' => $file,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['url', 'name', 'type']);
        
        Storage::disk('public')->assertExists('support_attachments/' . $file->hashName());
    }

    public function test_super_admin_can_upload_attachment()
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('logs.txt', 150);

        $response = $this->actingAs($this->superAdmin, 'api')
            ->postJson('/api/super-admin/support/upload', [
                'file' => $file,
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['url', 'name', 'type']);

        Storage::disk('public')->assertExists('support_attachments/' . $file->hashName());
    }

    public function test_gym_admin_can_create_ticket_with_attachments()
    {
        Storage::fake('public');

        $response = $this->actingAs($this->gymAdmin, 'api')
            ->postJson('/api/gym/support/tickets', [
                'subject' => 'Issue with checkout flow',
                'description' => 'The payment page fails to load completely.',
                'priority' => 'medium',
                'attachments' => [
                    [
                        'url' => 'storage/support_attachments/screenshot.png',
                        'name' => 'screenshot.png',
                        'type' => 'image/png'
                    ]
                ]
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('support_tickets', [
            'subject' => 'Issue with checkout flow',
            'attachments' => json_encode([
                [
                    'url' => 'storage/support_attachments/screenshot.png',
                    'name' => 'screenshot.png',
                    'type' => 'image/png'
                ]
            ])
        ]);
    }

    public function test_gym_admin_can_reply_to_ticket()
    {
        $ticket = SupportTicket::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->gymAdmin->id,
            'subject' => 'Database Backup Issue',
            'description' => 'The backup failed with status 500.',
            'priority' => 'high',
            'status' => 'resolved', // Set to resolved to test auto-reopen
        ]);

        $response = $this->actingAs($this->gymAdmin, 'api')
            ->postJson("/api/gym/support/tickets/{$ticket->id}/reply", [
                'message' => 'Please help reopen and look at this.',
            ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('support_ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $this->gymAdmin->id,
            'message' => 'Please help reopen and look at this.',
        ]);

        // Status should automatically transition back to open
        $ticket->refresh();
        $this->assertEquals('open', $ticket->status);
    }

    public function test_super_admin_can_view_all_tickets_and_reply()
    {
        $ticket = SupportTicket::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->gymAdmin->id,
            'subject' => 'Database Backup Issue',
            'description' => 'The backup failed with status 500.',
            'priority' => 'high',
            'status' => 'open',
        ]);

        // Get tickets list
        $responseList = $this->actingAs($this->superAdmin, 'api')
            ->getJson('/api/super-admin/support/tickets');

        $responseList->assertStatus(200);
        $responseList->assertJsonCount(1, 'data');

        // Post support reply and update status
        $responseReply = $this->actingAs($this->superAdmin, 'api')
            ->postJson("/api/super-admin/support/tickets/{$ticket->id}/reply", [
                'message' => 'We are investigating this.',
                'status' => 'in_progress',
            ]);

        $responseReply->assertStatus(201);

        $this->assertDatabaseHas('support_ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $this->superAdmin->id,
            'message' => 'We are investigating this.',
        ]);

        $ticket->refresh();
        $this->assertEquals('in_progress', $ticket->status);
    }

    public function test_super_admin_can_reply_with_attachments()
    {
        $ticket = SupportTicket::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->gymAdmin->id,
            'subject' => 'Database Backup Issue',
            'description' => 'The backup failed with status 500.',
            'priority' => 'high',
            'status' => 'open',
        ]);

        $response = $this->actingAs($this->superAdmin, 'api')
            ->postJson("/api/super-admin/support/tickets/{$ticket->id}/reply", [
                'message' => 'Take a look at these logs.',
                'attachments' => [
                    [
                        'url' => 'storage/support_attachments/server_logs.txt',
                        'name' => 'server_logs.txt',
                        'type' => 'text/plain'
                    ]
                ]
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('support_ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $this->superAdmin->id,
            'message' => 'Take a look at these logs.',
            'attachments' => json_encode([
                [
                    'url' => 'storage/support_attachments/server_logs.txt',
                    'name' => 'server_logs.txt',
                    'type' => 'text/plain'
                ]
            ])
        ]);
    }
}
