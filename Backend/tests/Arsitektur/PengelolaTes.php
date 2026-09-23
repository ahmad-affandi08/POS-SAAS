<?php

declare(strict_types=1);

use App\Domain\Pengelola\TimInternal\Model\LogAuditPengelola;

/*
 * Test arsitektur Platform Pengelola (PRD §13.8, BR-P01.3, BR-P01.4). File penjaga: hanya manusia yang mengubah.
 */

arch('kode tenant tidak memakai domain Pengelola')
    ->expect('App\Domain\Pengelola')
    ->toOnlyBeUsedIn([
        'App\Domain\Pengelola',
        'App\Http\Kontroler\Pengelola',
        'App\Http\Perantara\Pengelola',
        'App\Http\Permintaan\Pengelola',
        'App\Console\Perintah',
        'App\Providers',
        'Database',
    ]);

arch('LogAuditPengelola hanya ditulis lewat PencatatAuditPengelola dan dibaca di halaman log audit')
    ->expect(LogAuditPengelola::class)
    ->toOnlyBeUsedIn([
        'App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola',
        'App\Http\Kontroler\Pengelola\LogAuditKontroler',
    ]);
