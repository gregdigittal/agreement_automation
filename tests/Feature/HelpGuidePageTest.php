<?php

use App\Models\User;
use Filament\Facades\Filament;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('any authenticated user can access help guide page', function () {
    foreach (['system_admin', 'legal', 'commercial', 'finance', 'operations', 'audit'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);
        $this->actingAs($user);

        $this->get('/admin/help-guide-page')
            ->assertSuccessful();
    }
});

it('help guide page contains key sections', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    $this->actingAs($admin);

    $response = $this->get('/admin/help-guide-page');
    $response->assertSuccessful();
    $response->assertSee('Getting Started');
    $response->assertSee('Organisation Structure');
    $response->assertSee('Contract Lifecycle');
    $response->assertSee('Role Permissions');
    $response->assertSee('Frequently Asked Questions');
});

it('help guide page contains role permissions table', function () {
    $admin = User::factory()->create();
    $admin->assignRole('system_admin');
    $this->actingAs($admin);

    $response = $this->get('/admin/help-guide-page');
    $response->assertSee('System Admin');
    $response->assertSee('View Contracts');
    $response->assertSee('Workflow Templates');
});
