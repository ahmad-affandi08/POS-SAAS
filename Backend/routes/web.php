<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Rute back-office (/kelola/...) dan web publik ditambahkan per flow (PRD §13.6, D-06).
Route::get('/', fn () => Inertia::render('Beranda'))->name('beranda');
