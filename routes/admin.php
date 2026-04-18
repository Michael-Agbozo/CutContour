<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->group(function () {
    Route::livewire('/', 'pages::admin.dashboard')->name('admin.dashboard');

    Route::livewire('jobs', 'pages::admin.jobs')->name('admin.jobs');

    Route::livewire('jobs/failed', 'pages::admin.failed-jobs')->name('admin.failed-jobs');

    Route::livewire('users', 'pages::admin.users')->name('admin.users');

    Route::livewire('system', 'pages::admin.system')->name('admin.system');

    Route::livewire('logs', 'pages::admin.logs')->name('admin.logs');
});
