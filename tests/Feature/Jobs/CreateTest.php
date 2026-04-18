<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

test('guests are redirected to login from job creation page', function () {
    $this->get(route('jobs.create'))->assertRedirect(route('login'));
});

test('authenticated verified users can access job creation page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('jobs.create'))
        ->assertOk();
});

test('generate rejects dimensions exceeding max pixels', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('test.jpg', 100, 'image/jpeg');

    // 50 inches * 96 dpi = 4800 px > 4096 max
    Livewire::actingAs($user)
        ->test('pages::jobs.create')
        ->set('file', $file)
        ->set('unit', 'in')
        ->set('targetWidth', 50.0)
        ->set('targetHeight', 5.0)
        ->set('offsetValue', 0.125)
        ->call('generate')
        ->assertHasErrors(['targetWidth']);
});

test('generate accepts dimensions within max pixels', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('test.jpg', 100, 'image/jpeg');

    // 42 inches * 96 dpi = 4032 px < 4096 max
    Livewire::actingAs($user)
        ->test('pages::jobs.create')
        ->set('file', $file)
        ->set('unit', 'in')
        ->set('targetWidth', 42.0)
        ->set('targetHeight', 42.0)
        ->set('offsetValue', 0.125)
        ->call('generate')
        ->assertHasNoErrors(['targetWidth', 'targetHeight']);
});
