<?php

use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('system_admin can access all resource list pages', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    $this->actingAs($admin);

    $this->get('/admin/contracts')->assertSuccessful();
    $this->get('/admin/counterparties')->assertSuccessful();
    $this->get('/admin/entities')->assertSuccessful();
    $this->get('/admin/projects')->assertSuccessful();
    $this->get('/admin/regions')->assertSuccessful();
    $this->get('/admin/jurisdictions')->assertSuccessful();
    $this->get('/admin/workflow-templates')->assertSuccessful();
    $this->get('/admin/wiki-contracts')->assertSuccessful();
    $this->get('/admin/kyc-templates')->assertSuccessful();
    $this->get('/admin/signing-authorities')->assertSuccessful();
    $this->get('/admin/vendor-users')->assertSuccessful();
    $this->get('/admin/override-requests')->assertSuccessful();
    $this->get('/admin/audit-logs')->assertSuccessful();
    $this->get('/admin/notifications')->assertSuccessful();
    $this->get('/admin/merchant-agreements')->assertSuccessful();
});

it('system_admin can access all create pages', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    $this->actingAs($admin);

    $this->get('/admin/contracts/create')->assertSuccessful();
    $this->get('/admin/counterparties/create')->assertSuccessful();
    $this->get('/admin/entities/create')->assertSuccessful();
    $this->get('/admin/projects/create')->assertSuccessful();
    $this->get('/admin/regions/create')->assertSuccessful();
    $this->get('/admin/jurisdictions/create')->assertSuccessful();
    $this->get('/admin/workflow-templates/create')->assertSuccessful();
    $this->get('/admin/wiki-contracts/create')->assertSuccessful();
    $this->get('/admin/signing-authorities/create')->assertSuccessful();
    $this->get('/admin/override-requests/create')->assertSuccessful();
});

it('system_admin can access all page routes', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    $this->actingAs($admin);

    $this->get('/admin/reports-page')->assertSuccessful();
    $this->get('/admin/escalations-page')->assertSuccessful();
    $this->get('/admin/key-dates-page')->assertSuccessful();
    $this->get('/admin/reminders-page')->assertSuccessful();
    $this->get('/admin/bulk-contract-upload-page')->assertSuccessful();
    $this->get('/admin/org-visualization-page')->assertSuccessful();
    $this->get('/admin/notification-preferences-page')->assertSuccessful();
});

it('legal role can create contracts, counterparties, wiki-contracts, override-requests', function () {
    $legal = User::factory()->create();
    $legal->assignRole('legal');
    $this->actingAs($legal);

    $this->get('/admin/contracts/create')->assertSuccessful();
    $this->get('/admin/counterparties/create')->assertSuccessful();
    $this->get('/admin/wiki-contracts/create')->assertSuccessful();
    $this->get('/admin/override-requests/create')->assertSuccessful();
});

it('legal role can access page routes for reports, escalations, key-dates, reminders', function () {
    $legal = User::factory()->create();
    $legal->assignRole('legal');
    $this->actingAs($legal);

    $this->get('/admin/reports-page')->assertSuccessful();
    $this->get('/admin/escalations-page')->assertSuccessful();
    $this->get('/admin/key-dates-page')->assertSuccessful();
    $this->get('/admin/reminders-page')->assertSuccessful();
});

it('legal role cannot create admin-only resources', function () {
    $legal = User::factory()->create();
    $legal->assignRole('legal');
    $this->actingAs($legal);

    $this->get('/admin/entities/create')->assertForbidden();
    $this->get('/admin/projects/create')->assertForbidden();
    $this->get('/admin/regions/create')->assertForbidden();
    $this->get('/admin/jurisdictions/create')->assertForbidden();
    $this->get('/admin/signing-authorities/create')->assertForbidden();
});

it('commercial role can create contracts and counterparties', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole('commercial');
    $this->actingAs($commercial);

    $this->get('/admin/contracts/create')->assertSuccessful();
    $this->get('/admin/counterparties/create')->assertSuccessful();
});

it('commercial role cannot create wiki-contracts, entities, or override-requests', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole('commercial');
    $this->actingAs($commercial);

    $this->get('/admin/wiki-contracts/create')->assertForbidden();
    $this->get('/admin/entities/create')->assertForbidden();
    $this->get('/admin/override-requests/create')->assertForbidden();
    $this->get('/admin/signing-authorities/create')->assertForbidden();
});

it('finance role can access contracts list and reports page', function () {
    $finance = User::factory()->create();
    $finance->assignRole('finance');
    $this->actingAs($finance);

    $this->get('/admin/contracts')->assertSuccessful();
    $this->get('/admin/reports-page')->assertSuccessful();
    $this->get('/admin/notifications')->assertSuccessful();
});

it('finance role cannot create contracts or admin resources', function () {
    $finance = User::factory()->create();
    $finance->assignRole('finance');
    $this->actingAs($finance);

    $this->get('/admin/contracts/create')->assertForbidden();
    $this->get('/admin/counterparties/create')->assertForbidden();
    $this->get('/admin/entities/create')->assertForbidden();
});

it('operations role can access contracts list and key-dates/reminders pages', function () {
    $ops = User::factory()->create();
    $ops->assignRole('operations');
    $this->actingAs($ops);

    $this->get('/admin/contracts')->assertSuccessful();
    $this->get('/admin/key-dates-page')->assertSuccessful();
    $this->get('/admin/reminders-page')->assertSuccessful();
    $this->get('/admin/notifications')->assertSuccessful();
});

it('operations role cannot create contracts or admin resources', function () {
    $ops = User::factory()->create();
    $ops->assignRole('operations');
    $this->actingAs($ops);

    $this->get('/admin/contracts/create')->assertForbidden();
    $this->get('/admin/entities/create')->assertForbidden();
    $this->get('/admin/signing-authorities/create')->assertForbidden();
});

it('audit role can access contracts list, audit-logs, and reports', function () {
    $auditor = User::factory()->create();
    $auditor->assignRole('audit');
    $this->actingAs($auditor);

    $this->get('/admin/contracts')->assertSuccessful();
    $this->get('/admin/audit-logs')->assertSuccessful();
    $this->get('/admin/reports-page')->assertSuccessful();
    $this->get('/admin/notifications')->assertSuccessful();
});

it('audit role cannot create contracts or admin resources', function () {
    $auditor = User::factory()->create();
    $auditor->assignRole('audit');
    $this->actingAs($auditor);

    $this->get('/admin/contracts/create')->assertForbidden();
    $this->get('/admin/entities/create')->assertForbidden();
    $this->get('/admin/counterparties/create')->assertForbidden();
});

it('bulk upload page restricted to system_admin', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole('commercial');
    $this->actingAs($commercial);

    $this->get('/admin/bulk-contract-upload-page')->assertForbidden();
});
