<?php

use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// ── System Admin sees all nav items ──────────────────────────────────────

it('system admin sees all navigation groups', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    $this->actingAs($admin);

    $response = $this->get('/admin');
    $response->assertSuccessful();
    $response->assertSee('Contracts');
    $response->assertSee('Counterparties');
    $response->assertSee('Administration');
});

// ── Admin-only resources hidden from non-admins ──────────────────────────

it('commercial user cannot access region create page', function () {
    $user = User::factory()->create();
    $user->assignRole('commercial');
    $this->actingAs($user);

    $this->get('/admin/regions/create')->assertForbidden();
});

it('commercial user cannot access entity create page', function () {
    $user = User::factory()->create();
    $user->assignRole('commercial');
    $this->actingAs($user);

    $this->get('/admin/entities/create')->assertForbidden();
});

it('commercial user cannot access project create page', function () {
    $user = User::factory()->create();
    $user->assignRole('commercial');
    $this->actingAs($user);

    $this->get('/admin/projects/create')->assertForbidden();
});

it('commercial user cannot access workflow template create page', function () {
    $user = User::factory()->create();
    $user->assignRole('commercial');
    $this->actingAs($user);

    $this->get('/admin/workflow-templates/create')->assertForbidden();
});

it('finance user cannot access jurisdiction create page', function () {
    $user = User::factory()->create();
    $user->assignRole('finance');
    $this->actingAs($user);

    $this->get('/admin/jurisdictions/create')->assertForbidden();
});

it('finance user cannot access signing authority create page', function () {
    $user = User::factory()->create();
    $user->assignRole('finance');
    $this->actingAs($user);

    $this->get('/admin/signing-authorities/create')->assertForbidden();
});

// ── shouldRegisterNavigation checks ──────────────────────────────────────

it('region resource registers nav for system admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    $this->actingAs($admin);

    expect(\App\Filament\Resources\RegionResource::shouldRegisterNavigation())->toBeTrue();
});

it('region resource hides nav for commercial', function () {
    $user = User::factory()->create();
    $user->assignRole('commercial');
    $this->actingAs($user);

    expect(\App\Filament\Resources\RegionResource::shouldRegisterNavigation())->toBeFalse();
});

it('contract resource registers nav for all roles', function () {
    foreach (['system_admin', 'legal', 'commercial', 'finance', 'operations', 'audit'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        expect(\App\Filament\Resources\ContractResource::shouldRegisterNavigation())
            ->toBeTrue("Expected ContractResource nav visible for {$role}");
    }
});

it('counterparty resource registers nav for correct roles', function () {
    $visibleRoles = ['system_admin', 'legal', 'commercial'];
    $hiddenRoles = ['finance', 'operations', 'audit'];

    foreach ($visibleRoles as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
        expect(\App\Filament\Resources\CounterpartyResource::shouldRegisterNavigation())
            ->toBeTrue("Expected CounterpartyResource nav visible for {$role}");
    }

    foreach ($hiddenRoles as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
        expect(\App\Filament\Resources\CounterpartyResource::shouldRegisterNavigation())
            ->toBeFalse("Expected CounterpartyResource nav hidden for {$role}");
    }
});

it('audit log resource registers nav for correct roles', function () {
    $visibleRoles = ['system_admin', 'legal', 'audit'];
    $hiddenRoles = ['commercial', 'finance', 'operations'];

    foreach ($visibleRoles as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
        expect(\App\Filament\Resources\AuditLogResource::shouldRegisterNavigation())
            ->toBeTrue("Expected AuditLogResource nav visible for {$role}");
    }

    foreach ($hiddenRoles as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
        expect(\App\Filament\Resources\AuditLogResource::shouldRegisterNavigation())
            ->toBeFalse("Expected AuditLogResource nav hidden for {$role}");
    }
});

it('wiki contract resource registers nav for system admin and legal only', function () {
    $visibleRoles = ['system_admin', 'legal'];
    $hiddenRoles = ['commercial', 'finance', 'operations', 'audit'];

    foreach ($visibleRoles as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
        expect(\App\Filament\Resources\WikiContractResource::shouldRegisterNavigation())
            ->toBeTrue("Expected WikiContractResource nav visible for {$role}");
    }

    foreach ($hiddenRoles as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
        expect(\App\Filament\Resources\WikiContractResource::shouldRegisterNavigation())
            ->toBeFalse("Expected WikiContractResource nav hidden for {$role}");
    }
});

it('kyc template resource registers nav for system admin and legal only', function () {
    $visibleRoles = ['system_admin', 'legal'];
    $hiddenRoles = ['commercial', 'finance', 'operations', 'audit'];

    foreach ($visibleRoles as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
        expect(\App\Filament\Resources\KycTemplateResource::shouldRegisterNavigation())
            ->toBeTrue("Expected KycTemplateResource nav visible for {$role}");
    }

    foreach ($hiddenRoles as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
        expect(\App\Filament\Resources\KycTemplateResource::shouldRegisterNavigation())
            ->toBeFalse("Expected KycTemplateResource nav hidden for {$role}");
    }
});

it('notification resource registers nav for all roles', function () {
    foreach (['system_admin', 'legal', 'commercial', 'finance', 'operations', 'audit'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        expect(\App\Filament\Resources\NotificationResource::shouldRegisterNavigation())
            ->toBeTrue("Expected NotificationResource nav visible for {$role}");
    }
});

it('override request resource registers nav for system admin and legal only', function () {
    $visibleRoles = ['system_admin', 'legal'];
    $hiddenRoles = ['commercial', 'finance', 'operations', 'audit'];

    foreach ($visibleRoles as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
        expect(\App\Filament\Resources\OverrideRequestResource::shouldRegisterNavigation())
            ->toBeTrue("Expected OverrideRequestResource nav visible for {$role}");
    }

    foreach ($hiddenRoles as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
        expect(\App\Filament\Resources\OverrideRequestResource::shouldRegisterNavigation())
            ->toBeFalse("Expected OverrideRequestResource nav hidden for {$role}");
    }
});
