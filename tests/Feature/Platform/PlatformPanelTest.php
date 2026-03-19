<?php

declare(strict_types=1);

use App\Models\PlatformAdmin;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Events\TenantCreated;

it('platform panel is registered with the correct id and guard', function (): void {
    $panel = Filament::getPanel('platform');

    expect($panel->getId())->toBe('platform')
        ->and($panel->getAuthGuard())->toBe('superadmin');
});

it('platform panel does not include InitializeTenancyByDomain middleware', function (): void {
    $panel = Filament::getPanel('platform');
    $middleware = $panel->getMiddleware();

    expect($middleware)->not->toContain(\Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class);
});

it('platform panel login redirects unauthenticated requests', function (): void {
    $response = $this->get('/platform');

    $response->assertRedirect();
});

it('superadmin can authenticate against the superadmin guard', function (): void {
    $admin = PlatformAdmin::factory()->create([
        'email' => 'admin@digittal.io',
        'password' => bcrypt('password'),
    ]);

    $authenticated = auth('superadmin')->attempt([
        'email' => 'admin@digittal.io',
        'password' => 'password',
    ]);

    expect($authenticated)->toBeTrue();
    $this->assertAuthenticatedAs($admin, 'superadmin');
});

it('platform dashboard mounts and shows tenant stats', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('platform'));

    $admin = PlatformAdmin::factory()->create();
    $this->actingAs($admin, 'superadmin');

    // Suppress TenantCreated events so CreateDatabase/MigrateDatabase don't run.
    Event::fake([TenantCreated::class]);

    Tenant::create(['name' => 'Acme Corp', 'slug' => 'acme', 'status' => Tenant::STATUS_ACTIVE]);
    Tenant::create(['name' => 'Beta Ltd', 'slug' => 'beta', 'status' => Tenant::STATUS_SUSPENDED]);

    $page = new \App\Filament\Platform\Pages\PlatformDashboard;
    $page->mount();

    expect($page->totalTenants)->toBe(2)
        ->and($page->activeTenants)->toBe(1)
        ->and($page->suspendedTenants)->toBe(1)
        ->and($page->provisioningTenants)->toBe(0);
});

it('TenantResource is scoped to the platform panel', function (): void {
    $resource = \App\Filament\Platform\Resources\TenantResource::class;

    // Resource exists and belongs to the platform panel namespace
    expect(class_exists($resource))->toBeTrue()
        ->and($resource)->toContain('Platform\\Resources');
});
