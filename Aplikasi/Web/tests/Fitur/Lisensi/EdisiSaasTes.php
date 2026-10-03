<?php

declare(strict_types=1);

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Integrasi\Aksi\SimpanKonfigurasiIntegrasi;
use App\Domain\Pengelola\Integrasi\Data\DataKonfigurasiIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\JenisIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\LingkunganIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\PenyediaIntegrasi;
use App\Domain\Pengelola\Integrasi\Model\KonfigurasiIntegrasi;

/*
 * D-35: jalan pintas edisi Lisensi (data bawaan langsung terbit, integrasi tanpa pelaku konsol) tertutup di edisi SaaS,
 * tempat four-eyes & audit pengelola tetap berlaku.
 */
describe('Edisi SaaS menolak jalan pintas edisi Lisensi (D-35)', function (): void {
    it('lisensi:siapkan-data dan lisensi:atur-integrasi ditolak', function (): void {
        $this->artisan('lisensi:siapkan-data')->expectsOutputToContain('hanya di edisi Lisensi')->assertFailed();
        $this->artisan('lisensi:atur-integrasi', ['jenis' => 'Email'])->expectsOutputToContain('hanya untuk edisi Lisensi')->assertFailed();
    });

    it('menyimpan konfigurasi integrasi tanpa anggota Platform Pengelola ditolak', function (): void {
        $data = new DataKonfigurasiIntegrasi(JenisIntegrasi::Whatsapp, LingkunganIntegrasi::Staging, [], ['Token' => 'x'], 90, null, PenyediaIntegrasi::Fonnte);

        expect(fn () => app(SimpanKonfigurasiIntegrasi::class)->Jalankan(null, $data))->toThrow(PelanggaranAturanBisnis::class, 'Platform Pengelola')
            ->and(KonfigurasiIntegrasi::query()->count())->toBe(0);
    });
});
