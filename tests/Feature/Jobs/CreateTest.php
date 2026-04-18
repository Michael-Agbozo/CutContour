<?php

use App\Models\CutJob;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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

test('generate shows safe error message for non-RuntimeException', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('test.jpg', 100, 'image/jpeg');

    // Drop a column needed by CutJob::create to cause a QueryException
    // (usage count query still works since it doesn't use this column)
    Schema::table('cut_jobs', fn ($t) => $t->dropColumn('original_name'));

    $component = Livewire::actingAs($user)
        ->test('pages::jobs.create')
        ->set('file', $file)
        ->set('targetWidth', 5.0)
        ->set('targetHeight', 5.0)
        ->set('offsetValue', 0.125)
        ->call('generate');

    $component->assertSet('state', 'failed');
    // Should NOT expose internal exception details like column names or SQL
    expect($component->get('errorMessage'))->not->toContain('original_name')
        ->and($component->get('errorMessage'))->toBe('Something went wrong while setting up your job. Please try again.');
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

test('generate rejects when monthly usage limit is reached', function () {
    config(['cutjob.monthly_job_limit' => 2]);

    $user = User::factory()->create();

    // Create 2 completed jobs this month (at limit)
    CutJob::factory()->for($user)->completed()->count(2)->create();

    $file = UploadedFile::fake()->create('test.jpg', 100, 'image/jpeg');

    Livewire::actingAs($user)
        ->test('pages::jobs.create')
        ->set('file', $file)
        ->set('targetWidth', 5.0)
        ->set('targetHeight', 5.0)
        ->set('offsetValue', 0.125)
        ->call('generate')
        ->assertHasErrors(['file']);
});

test('generate allows jobs after admin resets usage', function () {
    config(['cutjob.monthly_job_limit' => 2]);

    $user = User::factory()->create();

    // Create 2 completed jobs before reset (at limit)
    CutJob::factory()->for($user)->completed()->count(2)->create([
        'created_at' => now()->subHours(2),
    ]);

    // Admin resets usage
    $user->update(['usage_reset_at' => now()->subHour()]);

    $file = UploadedFile::fake()->create('test.jpg', 100, 'image/jpeg');

    Livewire::actingAs($user)
        ->test('pages::jobs.create')
        ->set('file', $file)
        ->set('targetWidth', 5.0)
        ->set('targetHeight', 5.0)
        ->set('offsetValue', 0.125)
        ->call('generate')
        ->assertHasNoErrors(['file']);
});

test('generate rejects dimensions exceeding max cm limit', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('test.jpg', 100, 'image/jpeg');

    // 301 cm exceeds default 300 cm max
    Livewire::actingAs($user)
        ->test('pages::jobs.create')
        ->set('file', $file)
        ->set('unit', 'cm')
        ->set('targetWidth', 301.0)
        ->set('targetHeight', 100.0)
        ->set('offsetValue', 0.125)
        ->call('generate')
        ->assertHasErrors(['targetWidth']);
});

test('generate accepts dimensions within max cm limit', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('test.jpg', 100, 'image/jpeg');

    // 299 cm is within default 300 cm max
    Livewire::actingAs($user)
        ->test('pages::jobs.create')
        ->set('file', $file)
        ->set('unit', 'cm')
        ->set('targetWidth', 299.0)
        ->set('targetHeight', 299.0)
        ->set('offsetValue', 0.125)
        ->call('generate')
        ->assertHasNoErrors(['targetWidth', 'targetHeight']);
});

test('generate shows max error in selected unit', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('test.jpg', 100, 'image/jpeg');

    // 120 inches ≈ 304.8 cm which exceeds 300 cm max
    $component = Livewire::actingAs($user)
        ->test('pages::jobs.create')
        ->set('file', $file)
        ->set('unit', 'in')
        ->set('targetWidth', 120.0)
        ->set('targetHeight', 5.0)
        ->set('offsetValue', 0.125)
        ->call('generate');

    $errors = $component->errors();
    expect($errors->first('targetWidth'))->toContain('in');
});

test('download streams the completed job PDF via Livewire', function () {
    Storage::fake();

    $user = User::factory()->create();
    $job = CutJob::factory()->for($user)->completed()->create([
        'output_path' => 'users/'.$user->id.'/jobs/test123/output.pdf',
        'job_name' => 'my-poster',
        'width' => 3000,
        'height' => 3000,
        'unit' => 'cm',
    ]);

    Storage::put($job->output_path, 'fake-pdf-content');

    Livewire::actingAs($user)
        ->test('pages::jobs.create')
        ->set('completedJobId', $job->id)
        ->call('download')
        ->assertFileDownloaded('my-poster_25.4cmh_25.4cmw.pdf');
});

test('download filename uses Cut{N}-untitled when no job name is set', function () {
    $user = User::factory()->create();

    // First unnamed job
    $job1 = CutJob::factory()->for($user)->completed()->create([
        'job_name' => null,
        'width' => 3000,
        'height' => 1500,
        'unit' => 'in',
    ]);

    expect($job1->downloadFilename())->toBe('Cut1-untitled_5inh_10inw.pdf');

    // Second unnamed job
    $job2 = CutJob::factory()->for($user)->completed()->create([
        'job_name' => null,
        'width' => 1500,
        'height' => 3000,
        'unit' => 'mm',
    ]);

    expect($job2->downloadFilename())->toBe('Cut2-untitled_254mmh_127mmw.pdf');
});

test('download filename includes dimensions in user-selected unit', function () {
    $user = User::factory()->create();

    $job = CutJob::factory()->for($user)->completed()->create([
        'job_name' => 'Logo Design',
        'width' => 9000,
        'height' => 6000,
        'unit' => 'cm',
    ]);

    expect($job->downloadFilename())->toBe('Logo Design_50.8cmh_76.2cmw.pdf');
});
