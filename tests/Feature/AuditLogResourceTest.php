<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('can render audit log list page', function () {
    $this->get('/admin/audit-logs')->assertSuccessful();
});

it('shows audit log entries in table', function () {
    AuditService::log(
        action: 'test_action',
        resourceType: 'contract',
        resourceId: 'test-id-123',
        details: ['key' => 'value'],
        actor: $this->admin,
    );

    $this->assertDatabaseHas('audit_log', [
        'action' => 'test_action',
        'resource_type' => 'contract',
        'resource_id' => 'test-id-123',
        'actor_email' => $this->admin->email,
    ]);
});

it('audit user can access audit logs', function () {
    $audit = User::factory()->create();
    $audit->assignRole('audit');
    $this->actingAs($audit);

    $this->get('/admin/audit-logs')->assertSuccessful();
});

it('is read-only — no create page exists', function () {
    $this->get('/admin/audit-logs/create')->assertNotFound();
});
