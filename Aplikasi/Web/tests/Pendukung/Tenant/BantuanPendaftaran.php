<?php

declare(strict_types=1);

namespace Tests\Pendukung\Tenant;

use App\Domain\Organisasi\Data\DataPemilikBaru;
use App\Domain\Pengelola\Katalog\Aksi\SiapkanKatalogBawaan;
use App\Domain\Tenant\Data\DataPendaftaran;
use App\Domain\Tenant\Enum\JenisDokumenLegal;
use App\Domain\Tenant\Enum\StatusDokumenLegal;
use App\Domain\Tenant\Enum\StatusPaket;
use App\Domain\Tenant\Model\DokumenLegal;
use App\Domain\Tenant\Model\Paket;

/**
 * Prasyarat F-00 untuk test: paket aktif (P-04) dan S&K + Kebijakan Privasi yang berlaku (P-06).
 */
final class BantuanPendaftaran
{
    /**
     * Tanggal berlaku dokumen legal prasyarat, **tetap** dan jauh di belakang setiap tanggal skenario di suite ini
     * (yang paling awal 31/01/2026).
     *
     * Sebelumnya nilainya `now()->subDay()`, yang dihitung dari jam dinding sungguhan karena pembantu ini dipanggil
     * di `beforeEach` sebelum test membekukan waktunya. Begitu tanggal nyata melewati tanggal skenario sebuah test,
     * dokumennya jadi "belum berlaku" dan `DaftarkanTenant` menolak dengan BR-P06.2 — test yang tadinya hijau mulai
     * merah sendiri tanpa ada kode yang berubah.
     */
    private const BERLAKU_MULAI = '2025-01-01';

    public static function SiapkanPrasyarat(): void
    {
        app(SiapkanKatalogBawaan::class)->Jalankan();
        Paket::query()->whereIn('Kode', ['GRATIS', 'STARTER', 'PRO', 'BISNIS', 'ENTERPRISE'])->update(['Status' => StatusPaket::Aktif->value]);

        foreach (JenisDokumenLegal::AmbilWajibRegistrasi() as $jenis) {
            DokumenLegal::query()->create([
                'Jenis' => $jenis,
                'Versi' => 1,
                'Judul' => $jenis->AmbilLabel(),
                'Isi' => "# {$jenis->AmbilLabel()}",
                'BerlakuMulai' => self::BERLAKU_MULAI,
                'Status' => StatusDokumenLegal::Terbit,
            ]);
        }
    }

    public static function Data(string $email = 'rina@kopinusantara.id', string $noHp = '081234567890', ?string $kodePaket = null, string $namaUsaha = 'Kopi Nusantara'): DataPendaftaran
    {
        return new DataPendaftaran(
            pemilik: new DataPemilikBaru(nama: 'Rina Wulandari', email: $email, noHp: $noHp, kataSandi: 'kata-sandi-kuat-123'),
            namaUsaha: $namaUsaha,
            kodePaket: $kodePaket,
            ip: '203.0.113.9',
        );
    }
}
