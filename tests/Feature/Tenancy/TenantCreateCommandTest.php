<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Support\Facades\Event;
use Stancl\Tenancy\Events\TenantCreated;

beforeEach(function (): void {
    Event::fake([TenantCreated::class]);
});

it('creates a tenant with valid name and slug', function (): void {
    $this->artisan('tenant:create', [
        '--name' => 'Acme Corp',
        '--slug' => 'acme',
    ])
        ->assertSuccessful();

    expect(Tenant::where('slug', 'acme')->exists())->toBeTrue();

    $tenant = Tenant::where('slug', 'acme')->first();
    expect($tenant->name)->toBe('Acme Corp')
        ->and($tenant->status)->toBe(Tenant::STATUS_ACTIVE);

    $domain = $tenant->domains()->where('domain', 'acme-ccrs.digittal.mobi')->first();
    expect($domain)->not->toBeNull();
});

it('fails with invalid slug characters', function (): void {
    $this->artisan('tenant:create', [
        '--name' => 'Bad Corp',
        '--slug' => 'Bad_Corp!',
    ])
        ->assertFailed();

    expect(Tenant::where('slug', 'Bad_Corp!')->exists())->toBeFalse();
});

it('fails when slug already exists', function (): void {
    Tenant::create([
        'name' => 'Existing Corp',
        'slug' => 'existing',
        'status' => Tenant::STATUS_ACTIVE,
    ]);

    $this->artisan('tenant:create', [
        '--name' => 'Another Corp',
        '--slug' => 'existing',
    ])
        ->assertFailed();

    expect(Tenant::where('slug', 'existing')->count())->toBe(1);
});

it('uses interactive prompts when options are not provided', function (): void {
    $this->artisan('tenant:create')
        ->expectsQuestion('Tenant name', 'Prompt Corp')
        ->expectsQuestion('Tenant slug (lowercase alphanumeric + hyphens)', 'prompt-corp')
        ->assertSuccessful();

    expect(Tenant::where('slug', 'prompt-corp')->exists())->toBeTrue();
});
