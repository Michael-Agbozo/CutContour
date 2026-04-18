<?php

use App\Http\Controllers\JobDownloadController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');

    Route::livewire('jobs/create', 'pages::jobs.create')->name('jobs.create');

    Route::livewire('notifications', 'pages::notifications.index')->name('notifications.index');
});

// Signed, time-limited download link — also requires auth so guests hit the login screen first
Route::get('jobs/{cutJob}/download', [JobDownloadController::class, 'download'])
    ->name('jobs.download')
    ->middleware(['auth', 'signed']);

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
