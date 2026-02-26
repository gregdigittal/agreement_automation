<?php

use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('can render reports page', function () {
    $this->get('/admin/reports-page')->assertSuccessful();
});

it('system_admin can access reports', function () {
    $this->get('/admin/reports-page')->assertSuccessful();
});

it('legal user can access reports', function () {
    $legal = User::factory()->create();
    $legal->assignRole('legal');
    $this->actingAs($legal);

    $this->get('/admin/reports-page')->assertSuccessful();
});

it('finance user can access reports', function () {
    $finance = User::factory()->create();
    $finance->assignRole('finance');
    $this->actingAs($finance);

    $this->get('/admin/reports-page')->assertSuccessful();
});

it('export routes exist', function () {
    $this->get(route('reports.export.contracts.excel'))
        ->assertSuccessful();
});
