<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;

it('creates audit log entry with correct fields', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    AuditService::log('create', 'Contract', 'test-uuid-123', ['title' => 'Test MSA']);

    $this->assertDatabaseHas('audit_log', [
        'action' => 'create',
        'resource_type' => 'Contract',
        'resource_id' => 'test-uuid-123',
        'actor_email' => $user->email,
    ]);

    $log = AuditLog::where('resource_id', 'test-uuid-123')->first();
    expect($log->details)->toBe(['title' => 'Test MSA']);
    expect($log->at)->not->toBeNull();
});

it('creates audit log with explicit actor', function () {
    $actor = User::factory()->create(['email' => 'explicit@digittal.io']);

    AuditService::log('update', 'Counterparty', 'cp-uuid', [], $actor);

    $this->assertDatabaseHas('audit_log', [
        'action' => 'update',
        'resource_type' => 'Counterparty',
        'resource_id' => 'cp-uuid',
        'actor_email' => 'explicit@digittal.io',
    ]);
});

it('audit log records are immutable - cannot update', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    AuditService::log('create', 'Contract', 'immutable-test');
    $log = AuditLog::where('resource_id', 'immutable-test')->first();

    expect(fn () => $log->update(['action' => 'delete']))
        ->toThrow(RuntimeException::class);
});

it('audit log records are immutable - cannot delete', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    AuditService::log('delete', 'Contract', 'delete-test');
    $log = AuditLog::where('resource_id', 'delete-test')->first();

    expect(fn () => $log->delete())
        ->toThrow(RuntimeException::class);
});

it('stores details as JSON and decodes correctly', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $details = [
        'old_status' => 'draft',
        'new_status' => 'active',
        'changed_fields' => ['workflow_state', 'title'],
    ];

    AuditService::log('state_change', 'Contract', 'json-test', $details);

    $log = AuditLog::where('resource_id', 'json-test')->first();
    expect($log->details)->toBeArray();
    expect($log->details['old_status'])->toBe('draft');
    expect($log->details['changed_fields'])->toBe(['workflow_state', 'title']);
});
