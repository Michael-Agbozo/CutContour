<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('billing page is displayed for authenticated users', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('billing.edit'))->assertOk();
});

test('guests are redirected to login from billing page', function () {
    $this->get(route('billing.edit'))->assertRedirect(route('login'));
});
