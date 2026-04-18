<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

test('guests are redirected to login from job creation page', function () {
    $this->get(route('jobs.create'))->assertRedirect(route('login'));
});

test('authenticated verified users can access job creation page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('jobs.create'))
        ->assertOk();
});

test('generate shows safe error message for non-RuntimeException', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('test.jpg', 100, 'image/jpeg');

    // Drop the cut_jobs table to cause a QueryException (not RuntimeException)
    Schema::drop('cut_jobs');

    $component = Livewire::actingAs($user)
        ->test('pages::jobs.create')
        ->set('file', $file)
        ->set('targetWidth', 5.0)
        ->set('targetHeight', 5.0)
        ->set('offsetValue', 0.125)
        ->call('generate');

    $component->assertSet('state', 'failed');
    // Should NOT expose internal exception details like table names or SQL
    expect($component->get('errorMessage'))->not->toContain('cut_jobs')
        ->and($component->get('errorMessage'))->toBe('An error occurred during processing. Please try again.');
});
