<?php

use App\Jobs\ProcessCutJob;
use App\Models\CutJob;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

test('retrying a failed job clears stale error_detail and pipeline_step', function () {
    Bus::fake();

    $admin = User::factory()->admin()->create();
    $job = CutJob::factory()->for(User::factory()->create())->create([
        'status' => 'failed',
        'error_message' => 'boom',
        'error_detail' => "Stack trace: line 1\nline 2",
        'pipeline_step' => 4,
        'processing_duration_ms' => 12345,
    ]);

    $this->actingAs($admin);

    Livewire::test('pages::admin.failed-jobs')
        ->call('retryJob', $job->id);

    $job->refresh();

    expect($job->status)->toBe('processing')
        ->and($job->error_message)->toBeNull()
        ->and($job->error_detail)->toBeNull()
        ->and($job->pipeline_step)->toBe(0)
        ->and($job->processing_duration_ms)->toBeNull();

    Bus::assertDispatched(ProcessCutJob::class);
});
