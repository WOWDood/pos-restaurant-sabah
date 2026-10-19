<?php

use App\Models\PaymentMethod;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($this->user);
});

it('can view payment methods page', function () {
    $this->get(route('payment-methods'))->assertOk();
});

it('can create a payment method', function () {
    Livewire\Livewire::test('pages::payment-methods')
        ->set('description', 'Gift Card')
        ->set('sort_order', 5)
        ->set('active', true)
        ->set('ref_required', false)
        ->call('save');

    expect(PaymentMethod::where('description', 'Gift Card')->exists())->toBeTrue();
});

it('can delete a payment method', function () {
    $pm = PaymentMethod::factory()->create(['description' => 'ToDelete']);

    Livewire\Livewire::test('pages::payment-methods')
        ->call('confirmDelete', $pm->id)
        ->call('delete');

    expect(PaymentMethod::find($pm->id))->toBeNull();
});