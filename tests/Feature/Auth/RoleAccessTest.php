<?php

use App\Models\Contract;
use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// ── 1. system_admin can access ALL navigation groups ─────────────────────────

it('system_admin can access all navigation groups and resource pages', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    $this->actingAs($admin);

    // Resource list pages
    $this->get('/admin/contracts')->assertSuccessful();
    $this->get('/admin/counterparties')->assertSuccessful();
    $this->get('/admin/entities')->assertSuccessful();
    $this->get('/admin/projects')->assertSuccessful();
    $this->get('/admin/regions')->assertSuccessful();
    $this->get('/admin/jurisdictions')->assertSuccessful();
    $this->get('/admin/signing-authorities')->assertSuccessful();
    $this->get('/admin/audit-logs')->assertSuccessful();
    $this->get('/admin/merchant-agreements')->assertSuccessful();
    $this->get('/admin/wiki-contracts')->assertSuccessful();
    $this->get('/admin/kyc-templates')->assertSuccessful();
    $this->get('/admin/override-requests')->assertSuccessful();
    $this->get('/admin/notifications')->assertSuccessful();
    $this->get('/admin/workflow-templates')->assertSuccessful();
    $this->get('/admin/vendor-users')->assertSuccessful();

    // Custom pages
    $this->get('/admin/reports-page')->assertSuccessful();
    $this->get('/admin/escalations-page')->assertSuccessful();
    $this->get('/admin/key-dates-page')->assertSuccessful();
    $this->get('/admin/reminders-page')->assertSuccessful();
    $this->get('/admin/bulk-contract-upload-page')->assertSuccessful();
    $this->get('/admin/org-visualization-page')->assertSuccessful();

    // Create pages
    $this->get('/admin/contracts/create')->assertSuccessful();
    $this->get('/admin/counterparties/create')->assertSuccessful();
    $this->get('/admin/entities/create')->assertSuccessful();
    $this->get('/admin/projects/create')->assertSuccessful();
    $this->get('/admin/regions/create')->assertSuccessful();
    $this->get('/admin/jurisdictions/create')->assertSuccessful();
    $this->get('/admin/signing-authorities/create')->assertSuccessful();
});

// ── 2. legal can access: Contracts, Counterparties, KYC, Compliance, Escalations, Reports ─

it('legal can access contracts, counterparties, KYC, escalations, and reports', function () {
    $user = User::factory()->create();
    $user->assignRole('legal');
    $this->actingAs($user);

    // Contracts group
    $this->get('/admin/contracts')->assertSuccessful();
    $this->get('/admin/contracts/create')->assertSuccessful();
    $this->get('/admin/merchant-agreements')->assertSuccessful();
    $this->get('/admin/key-dates-page')->assertSuccessful();
    $this->get('/admin/reminders-page')->assertSuccessful();

    // Counterparties group
    $this->get('/admin/counterparties')->assertSuccessful();
    $this->get('/admin/counterparties/create')->assertSuccessful();
    $this->get('/admin/override-requests')->assertSuccessful();
    $this->get('/admin/override-requests/create')->assertSuccessful();

    // KYC / Compliance
    $this->get('/admin/wiki-contracts')->assertSuccessful();
    $this->get('/admin/wiki-contracts/create')->assertSuccessful();
    $this->get('/admin/kyc-templates')->assertSuccessful();

    // Escalations
    $this->get('/admin/escalations-page')->assertSuccessful();

    // Reports
    $this->get('/admin/reports-page')->assertSuccessful();

    // Audit Logs
    $this->get('/admin/audit-logs')->assertSuccessful();
});

// ── 3. commercial can access: Contracts, Counterparties, Merchant Agreements, Key Dates, Reminders ─

it('commercial can access contracts, counterparties, merchant agreements, key dates, and reminders', function () {
    $user = User::factory()->create();
    $user->assignRole('commercial');
    $this->actingAs($user);

    // Contracts
    $this->get('/admin/contracts')->assertSuccessful();
    $this->get('/admin/contracts/create')->assertSuccessful();

    // Counterparties
    $this->get('/admin/counterparties')->assertSuccessful();
    $this->get('/admin/counterparties/create')->assertSuccessful();

    // Merchant Agreements
    $this->get('/admin/merchant-agreements')->assertSuccessful();

    // Key Dates & Reminders
    $this->get('/admin/key-dates-page')->assertSuccessful();
    $this->get('/admin/reminders-page')->assertSuccessful();
});

// ── 4. finance has VIEW-ONLY on Contracts, can access: Reports ───────────────

it('finance can view contracts and access reports but cannot create contracts', function () {
    $user = User::factory()->create();
    $user->assignRole('finance');
    $this->actingAs($user);

    // Can view contract list (view-only)
    $this->get('/admin/contracts')->assertSuccessful();

    // Reports
    $this->get('/admin/reports-page')->assertSuccessful();

    // Notifications
    $this->get('/admin/notifications')->assertSuccessful();

    // Cannot create contracts
    $this->get('/admin/contracts/create')->assertForbidden();

    // Cannot create counterparties
    $this->get('/admin/counterparties/create')->assertForbidden();
});

// ── 5. operations has VIEW-ONLY on Contracts, can access: Key Dates, Reminders ─

it('operations can view contracts and access key dates and reminders', function () {
    $user = User::factory()->create();
    $user->assignRole('operations');
    $this->actingAs($user);

    // View-only on contracts
    $this->get('/admin/contracts')->assertSuccessful();

    // Key Dates & Reminders
    $this->get('/admin/key-dates-page')->assertSuccessful();
    $this->get('/admin/reminders-page')->assertSuccessful();

    // Notifications
    $this->get('/admin/notifications')->assertSuccessful();

    // Cannot create contracts
    $this->get('/admin/contracts/create')->assertForbidden();
});

// ── 6. audit has VIEW-ONLY on Contracts, can access: Compliance, Audit Logs, Reports ─

it('audit can view contracts and access audit logs and reports', function () {
    $user = User::factory()->create();
    $user->assignRole('audit');
    $this->actingAs($user);

    // View-only on contracts
    $this->get('/admin/contracts')->assertSuccessful();

    // Audit Logs
    $this->get('/admin/audit-logs')->assertSuccessful();

    // Reports
    $this->get('/admin/reports-page')->assertSuccessful();

    // Notifications
    $this->get('/admin/notifications')->assertSuccessful();

    // Cannot create contracts
    $this->get('/admin/contracts/create')->assertForbidden();
});

// ── 7. finance user CANNOT create a contract (403) ───────────────────────────

it('finance user cannot create a contract via create page', function () {
    $user = User::factory()->create();
    $user->assignRole('finance');
    $this->actingAs($user);

    $this->get('/admin/contracts/create')->assertForbidden();
});

it('finance user cannot create a contract via canCreate check', function () {
    $user = User::factory()->create();
    $user->assignRole('finance');
    $this->actingAs($user);

    expect(\App\Filament\Resources\ContractResource::canCreate())->toBeFalse();
});

// ── 8. operations user CANNOT edit a contract (403) ──────────────────────────

it('operations user cannot edit a contract', function () {
    $user = User::factory()->create();
    $user->assignRole('operations');
    $this->actingAs($user);

    $contract = Contract::factory()->create();

    expect(\App\Filament\Resources\ContractResource::canEdit($contract))->toBeFalse();
});

it('operations user cannot access contract edit page', function () {
    $user = User::factory()->create();
    $user->assignRole('operations');
    $this->actingAs($user);

    $contract = Contract::factory()->create();

    $this->get("/admin/contracts/{$contract->id}/edit")->assertForbidden();
});

// ── 9. audit user CANNOT modify any data ─────────────────────────────────────

it('audit user cannot create contracts', function () {
    $user = User::factory()->create();
    $user->assignRole('audit');
    $this->actingAs($user);

    $this->get('/admin/contracts/create')->assertForbidden();
    expect(\App\Filament\Resources\ContractResource::canCreate())->toBeFalse();
});

it('audit user cannot edit contracts', function () {
    $user = User::factory()->create();
    $user->assignRole('audit');
    $this->actingAs($user);

    $contract = Contract::factory()->create();

    expect(\App\Filament\Resources\ContractResource::canEdit($contract))->toBeFalse();
});

it('audit user cannot delete contracts', function () {
    $user = User::factory()->create();
    $user->assignRole('audit');
    $this->actingAs($user);

    $contract = Contract::factory()->create();

    expect(\App\Filament\Resources\ContractResource::canDelete($contract))->toBeFalse();
});

it('audit user cannot create counterparties', function () {
    $user = User::factory()->create();
    $user->assignRole('audit');
    $this->actingAs($user);

    $this->get('/admin/counterparties/create')->assertForbidden();
});

it('audit user cannot create entities', function () {
    $user = User::factory()->create();
    $user->assignRole('audit');
    $this->actingAs($user);

    $this->get('/admin/entities/create')->assertForbidden();
});

// ── 10. commercial user CANNOT approve override requests (403) ───────────────

it('commercial user cannot approve override requests', function () {
    $user = User::factory()->create();
    $user->assignRole('commercial');
    $this->actingAs($user);

    // Override request nav hidden from commercial
    expect(\App\Filament\Resources\OverrideRequestResource::shouldRegisterNavigation())->toBeFalse();

    // Cannot create override requests via the resource
    expect(\App\Filament\Resources\OverrideRequestResource::canCreate())->toBeFalse();
});

it('commercial user cannot access override request create page', function () {
    $user = User::factory()->create();
    $user->assignRole('commercial');
    $this->actingAs($user);

    $this->get('/admin/override-requests/create')->assertForbidden();
});

// ── 11. Only system_admin can access Bulk Operations pages ───────────────────

it('only system_admin can access bulk contract upload page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    $this->actingAs($admin);
    $this->get('/admin/bulk-contract-upload-page')->assertSuccessful();
});

it('legal cannot access bulk contract upload page', function () {
    $user = User::factory()->create();
    $user->assignRole('legal');
    $this->actingAs($user);
    $this->get('/admin/bulk-contract-upload-page')->assertForbidden();
});

it('commercial cannot access bulk contract upload page', function () {
    $user = User::factory()->create();
    $user->assignRole('commercial');
    $this->actingAs($user);
    $this->get('/admin/bulk-contract-upload-page')->assertForbidden();
});

it('finance cannot access bulk contract upload page', function () {
    $user = User::factory()->create();
    $user->assignRole('finance');
    $this->actingAs($user);
    $this->get('/admin/bulk-contract-upload-page')->assertForbidden();
});

it('operations cannot access bulk contract upload page', function () {
    $user = User::factory()->create();
    $user->assignRole('operations');
    $this->actingAs($user);
    $this->get('/admin/bulk-contract-upload-page')->assertForbidden();
});

it('audit cannot access bulk contract upload page', function () {
    $user = User::factory()->create();
    $user->assignRole('audit');
    $this->actingAs($user);
    $this->get('/admin/bulk-contract-upload-page')->assertForbidden();
});

// ── 12. Only system_admin and legal can trigger AI analysis ──────────────────

it('system_admin can trigger AI analysis (canEdit grants access to AI action)', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    $this->actingAs($admin);

    $contract = Contract::factory()->create();

    // system_admin can edit contracts, which is prerequisite for AI analysis action
    expect(\App\Filament\Resources\ContractResource::canEdit($contract))->toBeTrue();
});

it('legal can trigger AI analysis (canEdit grants access to AI action)', function () {
    $user = User::factory()->create();
    $user->assignRole('legal');
    $this->actingAs($user);

    $contract = Contract::factory()->create();

    // Legal can edit contracts, which is prerequisite for AI analysis action
    expect(\App\Filament\Resources\ContractResource::canEdit($contract))->toBeTrue();
});

it('commercial cannot trigger AI analysis (cannot edit contracts)', function () {
    $user = User::factory()->create();
    $user->assignRole('commercial');
    $this->actingAs($user);

    $contract = Contract::factory()->create();

    // Commercial cannot edit contracts
    expect(\App\Filament\Resources\ContractResource::canEdit($contract))->toBeFalse();
});

it('finance cannot trigger AI analysis (cannot edit contracts)', function () {
    $user = User::factory()->create();
    $user->assignRole('finance');
    $this->actingAs($user);

    $contract = Contract::factory()->create();

    expect(\App\Filament\Resources\ContractResource::canEdit($contract))->toBeFalse();
});

it('audit cannot trigger AI analysis (cannot edit contracts)', function () {
    $user = User::factory()->create();
    $user->assignRole('audit');
    $this->actingAs($user);

    $contract = Contract::factory()->create();

    expect(\App\Filament\Resources\ContractResource::canEdit($contract))->toBeFalse();
});

// ── Navigation visibility: shouldRegisterNavigation checks ───────────────────

it('organization resources nav visible only for system_admin', function () {
    $resources = [
        \App\Filament\Resources\RegionResource::class,
        \App\Filament\Resources\EntityResource::class,
        \App\Filament\Resources\ProjectResource::class,
        \App\Filament\Resources\SigningAuthorityResource::class,
    ];

    // Visible for system_admin
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    $this->actingAs($admin);

    foreach ($resources as $resource) {
        expect($resource::shouldRegisterNavigation())
            ->toBeTrue("Expected {$resource} nav visible for system_admin");
    }

    // Hidden for all other roles
    foreach (['legal', 'commercial', 'finance', 'operations', 'audit'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        foreach ($resources as $resource) {
            expect($resource::shouldRegisterNavigation())
                ->toBeFalse("Expected {$resource} nav hidden for {$role}");
        }
    }
});

it('contract resource nav visible for all six roles', function () {
    foreach (['system_admin', 'legal', 'commercial', 'finance', 'operations', 'audit'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        expect(\App\Filament\Resources\ContractResource::shouldRegisterNavigation())
            ->toBeTrue("Expected ContractResource nav visible for {$role}");
    }
});

it('merchant agreement resource nav visible for system_admin, legal, commercial only', function () {
    $visibleRoles = ['system_admin', 'legal', 'commercial'];
    $hiddenRoles = ['finance', 'operations', 'audit'];

    foreach ($visibleRoles as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
        expect(\App\Filament\Resources\MerchantAgreementResource::shouldRegisterNavigation())
            ->toBeTrue("Expected MerchantAgreementResource nav visible for {$role}");
    }

    foreach ($hiddenRoles as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);
        expect(\App\Filament\Resources\MerchantAgreementResource::shouldRegisterNavigation())
            ->toBeFalse("Expected MerchantAgreementResource nav hidden for {$role}");
    }
});
