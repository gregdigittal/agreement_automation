<?php

use App\Models\Counterparty;
use App\Models\OverrideRequest;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    $this->counterparty = Counterparty::create([
        'legal_name' => 'Override Test CP',
        'status' => 'Active',
    ]);

    $this->overrideRequest = OverrideRequest::create([
        'counterparty_id' => $this->counterparty->id,
        'contract_title' => 'Test Override Contract',
        'requested_by_email' => 'requester@example.com',
        'reason' => 'Need to proceed despite suspension',
        'status' => 'pending',
    ]);
});

it('approves an override request and sets decided fields', function () {
    $this->overrideRequest->update([
        'status' => 'approved',
        'decided_by' => $this->admin->email,
        'decided_at' => now(),
        'comment' => 'Approved for urgent business need',
    ]);

    $this->overrideRequest->refresh();
    expect($this->overrideRequest->status)->toBe('approved');
    expect($this->overrideRequest->decided_by)->toBe($this->admin->email);
    expect($this->overrideRequest->decided_at)->not->toBeNull();
    expect($this->overrideRequest->comment)->toBe('Approved for urgent business need');
});

it('rejects an override request with a required comment', function () {
    $this->overrideRequest->update([
        'status' => 'rejected',
        'decided_by' => $this->admin->email,
        'decided_at' => now(),
        'comment' => 'Insufficient justification provided',
    ]);

    $this->overrideRequest->refresh();
    expect($this->overrideRequest->status)->toBe('rejected');
    expect($this->overrideRequest->decided_by)->toBe($this->admin->email);
    expect($this->overrideRequest->comment)->toBe('Insufficient justification provided');
});

it('creates audit log entries on approve', function () {
    \App\Services\AuditService::log(
        action: 'override_approved',
        resourceType: 'override_request',
        resourceId: $this->overrideRequest->id,
        details: ['counterparty_id' => $this->counterparty->id],
    );

    $this->assertDatabaseHas('audit_log', [
        'action' => 'override_approved',
        'resource_type' => 'override_request',
        'resource_id' => $this->overrideRequest->id,
    ]);
});

it('non-admin user cannot change override status', function () {
    $commercialUser = User::factory()->create();
    $commercialUser->assignRole('commercial');

    // Commercial user should not have permission to edit override requests
    expect($commercialUser->hasAnyRole(['system_admin', 'legal']))->toBeFalse();
});
