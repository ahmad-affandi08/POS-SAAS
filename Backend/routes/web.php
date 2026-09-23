<?php

declare(strict_types=1);

use App\Http\Perantara\BagikanDataInertia;
use App\Http\Perantara\Pengelola\BagikanDataInertiaPengelola;
use App\Http\Perantara\Pengelola\CatatAuditPengelola;
use App\Http\Perantara\Pengelola\TolakDomainPengelola;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Platform Pengelola di subdomain sendiri (PRD §13.8). Didaftarkan lebih dulu agar menang atas rute tenant.
Route::domain(config('pengelola.Domain'))
    ->middleware([BagikanDataInertiaPengelola::class, CatatAuditPengelola::class])
    ->group(base_path('routes/Pengelola.php'));

// Rute back-office (/kelola/...) dan web publik ditambahkan per flow (PRD §13.6, D-06).
Route::middleware([TolakDomainPengelola::class, BagikanDataInertia::class])->group(function (): void {
    Route::get('/', fn () => Inertia::render('Beranda'))->name('beranda');
});
