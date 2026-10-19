<?php

use App\Models\PaymentMethod;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->actingAs($this->user);
});

it('opens edit modal', function () {
    $pm = PaymentMethod::factory()->create(['description' => 'CashX']);

    Livewire\Livewire::test('pages::payment-methods')
        ->call('openEdit', $pm->id)
        ->assertSet('showModal', true)
        ->assertSet('editingId', $pm->id);
});