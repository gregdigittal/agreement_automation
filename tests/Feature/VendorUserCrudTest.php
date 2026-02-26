<?php

use App\Filament\Resources\VendorUserResource\Pages\CreateVendorUser;
use App\Filament\Resources\VendorUserResource\Pages\EditVendorUser;
use App\Filament\Resources\VendorUserResource\Pages\ListVendorUsers;
use App\Models\Counterparty;
use App\Models\User;
use App\Models\VendorUser;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->counterparty = Counterparty::create(['legal_name' => 'VU Test Corp', 'status' => 'Active']);
});

it('can render vendor user list page', function () {
    $this->get('/admin/vendor-users')->assertSuccessful();
});

it('can render vendor user create page', function () {
    $this->get('/admin/vendor-users/create')->assertSuccessful();
});

it('can create a vendor user via Livewire form', function () {
    Livewire::test(CreateVendorUser::class)
        ->fillForm([
            'name' => 'Jane Vendor',
            'email' => 'jane@vendorcorp.com',
            'counterparty_id' => $this->counterparty->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('vendor_users', [
        'name' => 'Jane Vendor',
        'email' => 'jane@vendorcorp.com',
        'counterparty_id' => $this->counterparty->id,
    ]);
});

it('validates required fields on vendor user create', function () {
    Livewire::test(CreateVendorUser::class)
        ->fillForm([
            'name' => null,
            'email' => null,
            'counterparty_id' => null,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'email' => 'required',
            'counterparty_id' => 'required',
        ]);
});

it('validates email uniqueness on vendor user create', function () {
    VendorUser::create([
        'name' => 'Existing',
        'email' => 'taken@vendorcorp.com',
        'counterparty_id' => $this->counterparty->id,
    ]);

    Livewire::test(CreateVendorUser::class)
        ->fillForm([
            'name' => 'Duplicate',
            'email' => 'taken@vendorcorp.com',
            'counterparty_id' => $this->counterparty->id,
        ])
        ->call('create')
        ->assertHasFormErrors(['email' => 'unique']);
});

it('can edit a vendor user via Livewire form', function () {
    $vu = VendorUser::create([
        'name' => 'Before Edit',
        'email' => 'before@vendor.com',
        'counterparty_id' => $this->counterparty->id,
    ]);

    Livewire::test(EditVendorUser::class, ['record' => $vu->getRouteKey()])
        ->fillForm([
            'name' => 'After Edit',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($vu->fresh()->name)->toBe('After Edit');
});

it('send login link action creates token and sends mail', function () {
    Mail::fake();

    $vu = VendorUser::create([
        'name' => 'Token Vendor',
        'email' => 'token@vendor.com',
        'counterparty_id' => $this->counterparty->id,
        'last_login_at' => now(),
    ]);

    Livewire::test(ListVendorUsers::class)
        ->callTableAction('send_invite', $vu);

    $this->assertDatabaseHas('vendor_login_tokens', [
        'vendor_user_id' => $vu->id,
    ]);

    Mail::assertSent(\App\Mail\VendorMagicLink::class, function ($mail) {
        return $mail->hasTo('token@vendor.com');
    });
});

it('blocks non-admin roles from accessing vendor users', function () {
    $legal = User::factory()->create();
    $legal->assignRole('legal');
    $this->actingAs($legal);

    $this->get('/admin/vendor-users')->assertForbidden();
});
