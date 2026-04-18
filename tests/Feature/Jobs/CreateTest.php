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

test('generate rejects job name longer than 255 characters', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('test.jpg', 100, 'image/jpeg');

    Livewire::actingAs($user)
        ->test('pages::jobs.create')
        ->set('file', $file)
        ->set('jobName', str_repeat('a', 256))
        ->set('targetWidth', 5.0)
        ->set('targetHeight', 5.0)
        ->set('offsetValue', 0.125)
        ->call('generate')
        ->assertHasErrors(['jobName' => 'max']);
});

test('generate accepts job name of 255 characters', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('test.jpg', 100, 'image/jpeg');

    Livewire::actingAs($user)
        ->test('pages::jobs.create')
        ->set('file', $file)
        ->set('jobName', str_repeat('a', 255))
        ->set('targetWidth', 5.0)
        ->set('targetHeight', 5.0)
        ->set('offsetValue', 0.125)
        ->call('generate')
        ->assertHasNoErrors(['jobName']);
});

test('file upload rejects files exceeding max size', function () {
    config(['cutjob.max_file_size_mb' => 1]); // 1 MB limit for test

    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('large.jpg', 2048, 'image/jpeg'); // 2 MB

    Livewire::actingAs($user)
        ->test('pages::jobs.create')
        ->set('file', $file)
        ->assertHasErrors(['file']);
});

test('file upload accepts files within max size', function () {
    config(['cutjob.max_file_size_mb' => 100]);

    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('small.jpg', 512, 'image/jpeg'); // 512 KB

    Livewire::actingAs($user)
        ->test('pages::jobs.create')
        ->set('file', $file)
        ->assertHasNoErrors(['file']);
});
