<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('jobs/create', 'pages::jobs.create')->name('jobs.create');
});

require __DIR__.'/settings.php';
