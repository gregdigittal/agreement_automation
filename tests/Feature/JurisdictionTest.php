<?php

use App\Models\Entity;
use App\Models\Jurisdiction;
use App\Models\EntityJurisdiction;
use App\Models\Region;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('creates a jurisdiction', function () {
    $jurisdiction = Jurisdiction::create([
        'name' => 'UAE - DIFC',
        'country_code' => 'AE',
        'regulatory_body' => 'DIFC Authority',
        'is_active' => true,
    ]);

    expect($jurisdiction)->toBeInstanceOf(Jurisdiction::class);
    expect($jurisdiction->name)->toBe('UAE - DIFC');
    expect($jurisdiction->country_code)->toBe('AE');
    expect($jurisdiction->is_active)->toBeTrue();
});

it('creates a jurisdiction via factory', function () {
    $jurisdiction = Jurisdiction::factory()->create();

    expect($jurisdiction->id)->not->toBeNull();
    expect($jurisdiction->name)->not->toBeNull();
    expect($jurisdiction->country_code)->toHaveLength(2);
});

it('links entity to jurisdiction with license data', function () {
    $region = Region::create(['name' => 'Test Region']);
    $entity = Entity::create(['region_id' => $region->id, 'name' => 'Test Entity']);
    $jurisdiction = Jurisdiction::factory()->create();

    $entity->jurisdictions()->attach($jurisdiction->id, [
        'id' => \Illuminate\Support\Str::uuid()->toString(),
        'license_number' => 'LIC-1234',
        'license_expiry' => '2027-12-31',
        'is_primary' => true,
    ]);

    expect($entity->jurisdictions)->toHaveCount(1);
    expect($entity->jurisdictions->first()->pivot->license_number)->toBe('LIC-1234');
    expect((bool) $entity->jurisdictions->first()->pivot->is_primary)->toBeTrue();
});

it('supports entity parent-child hierarchy', function () {
    $region = Region::create(['name' => 'Test Region']);
    $parent = Entity::create(['region_id' => $region->id, 'name' => 'Parent Corp']);
    $child = Entity::create([
        'region_id' => $region->id,
        'name' => 'Child Subsidiary',
        'parent_entity_id' => $parent->id,
    ]);

    expect($child->parent->id)->toBe($parent->id);
    expect($parent->children)->toHaveCount(1);
    expect($parent->children->first()->id)->toBe($child->id);
});

it('stores legal fields on entity', function () {
    $region = Region::create(['name' => 'Test Region']);
    $entity = Entity::create([
        'region_id' => $region->id,
        'name' => 'Digittal FZ-LLC',
        'legal_name' => 'Digittal Technology Solutions FZ-LLC',
        'registration_number' => 'REG-2024-001',
        'registered_address' => 'DIFC, Gate Avenue, Dubai, UAE',
    ]);

    expect($entity->legal_name)->toBe('Digittal Technology Solutions FZ-LLC');
    expect($entity->registration_number)->toBe('REG-2024-001');
    expect($entity->registered_address)->toContain('DIFC');
});
