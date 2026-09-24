<?php

declare(strict_types=1);

use Illuminate\Contracts\Queue\ShouldQueue;

/*
 * Test arsitektur tambahan F-03 (DesainF03 F, H18). Tidak terlindungi; usulan pemindahan ke tests/Arsitektur untuk
 * manusia. Kelas yang belum ada (PenentuHarga Tim 2, Tugas impor Tim 4) otomatis tercakup begitu dibuat.
 */

arch('PenentuHarga murni: tanpa facade, database, atau model katalog')
    ->expect('App\Domain\Katalog\Harga\Layanan\PenentuHarga')
    ->not->toUse(['Illuminate\Support\Facades', 'Illuminate\Database', 'App\Domain\Katalog\Model']);

arch('tugas impor katalog berjalan di antrean')
    ->expect('App\Domain\Katalog\Impor\Tugas')
    ->classes()
    ->toImplement(ShouldQueue::class);

arch('domain Katalog tidak memakai model Organisasi atau Persediaan (lewat Kueri/Aksi publik)')
    ->expect('App\Domain\Katalog')
    ->not->toUse(['App\Domain\Organisasi\Model', 'App\Domain\Persediaan\Model']);
