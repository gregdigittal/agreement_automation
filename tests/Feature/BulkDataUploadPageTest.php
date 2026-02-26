<?php

use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('system admin can access bulk data upload page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    $this->actingAs($admin);

    $this->get('/admin/bulk-data-upload-page')->assertSuccessful();
});

it('legal user cannot access bulk data upload page', function () {
    $user = User::factory()->create();
    $user->assignRole('legal');
    $this->actingAs($user);

    $this->get('/admin/bulk-data-upload-page')->assertForbidden();
});

it('commercial user cannot access bulk data upload page', function () {
    $user = User::factory()->create();
    $user->assignRole('commercial');
    $this->actingAs($user);

    $this->get('/admin/bulk-data-upload-page')->assertForbidden();
});

it('finance user cannot access bulk data upload page', function () {
    $user = User::factory()->create();
    $user->assignRole('finance');
    $this->actingAs($user);

    $this->get('/admin/bulk-data-upload-page')->assertForbidden();
});

it('page shows upload form content', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    $this->actingAs($admin);

    $response = $this->get('/admin/bulk-data-upload-page');
    $response->assertSuccessful();
    $response->assertSee('Bulk Data Upload');
});
