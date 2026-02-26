<?php

use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('dashboard renders successfully', function () {
    $this->get('/admin')->assertSuccessful();
});

it('agreement repository page renders', function () {
    $this->get('/admin/agreement-repository')->assertSuccessful();
});

it('bulk contract upload page renders for admin', function () {
    $this->get('/admin/bulk-contract-upload-page')->assertSuccessful();
});

it('bulk contract upload page blocked for non-admin', function () {
    $commercial = User::factory()->create();
    $commercial->assignRole('commercial');
    $this->actingAs($commercial);

    $this->get('/admin/bulk-contract-upload-page')->assertForbidden();
});

it('AI cost report page renders', function () {
    $this->get('/admin/ai-cost-report-page')->assertSuccessful();
});

it('reminders page renders', function () {
    $this->get('/admin/reminders-page')->assertSuccessful();
});

it('key dates page renders', function () {
    $this->get('/admin/key-dates-page')->assertSuccessful();
});

it('notifications page renders', function () {
    $this->get('/admin/notifications-page')->assertSuccessful();
});

it('escalations page renders', function () {
    $this->get('/admin/escalations-page')->assertSuccessful();
});

it('reports page renders', function () {
    $this->get('/admin/reports-page')->assertSuccessful();
});

it('org visualization page renders', function () {
    $this->get('/admin/org-visualization-page')->assertSuccessful();
});

it('analytics dashboard renders when feature enabled', function () {
    config(['features.advanced_analytics' => true]);

    $this->get('/admin/analytics-dashboard-page')->assertSuccessful();
});

it('analytics dashboard blocked when feature disabled', function () {
    config(['features.advanced_analytics' => false]);

    $this->get('/admin/analytics-dashboard-page')->assertForbidden();
});

it('notification preferences page renders', function () {
    $this->get('/admin/notification-preferences-page')->assertSuccessful();
});
