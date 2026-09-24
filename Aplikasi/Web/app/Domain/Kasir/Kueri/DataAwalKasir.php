<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Kueri;

use App\Domain\Kasir\Model\KategoriKas;
use App\Domain\Organisasi\Kueri\StafPerangkat;
use App\Domain\Organisasi\Layanan\VerifierPinOffline;
use App\Domain\Organisasi\Model\Perangkat;
use App\Domain\Tenant\Kueri\PengaturanKasirTenant;

/**
 * Isi `GET /api/pos/v1/data-awal` bagian F-06 (PRD §16.3): staf & verifier PIN offline, kategori kas aktif,
 * pengaturan kasir, dan parameter Argon2id. Katalog tetap lewat `/katalog` (F-03); bagian lain (promo, meja, metode
 * bayar) ditambahkan flow masing-masing.
 */
final class DataAwalKasir
{
    public function __construct(
        private readonly StafPerangkat $staf,
        private readonly PengaturanKasirTenant $pengaturan,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function Ambil(Perangkat $perangkat): array
    {
        $pengaturan = $this->pengaturan->Ambil();

        return [
            'Pengaturan' => [
                'BatasKasKeluar' => $pengaturan->batasKasKeluar->KeString(),
                'ShiftBersama' => $pengaturan->shiftBersama,
            ],
            'KategoriKas' => array_values(KategoriKas::query()
                ->where('Aktif', true)
                ->orderBy('Jenis')
                ->orderBy('Urutan')
                ->orderBy('Nama')
                ->get()
                ->map(fn (KategoriKas $k): array => ['Uuid' => $k->Uuid, 'Nama' => $k->Nama, 'Jenis' => $k->Jenis->value])
                ->all()),
            'Staf' => $this->staf->Ambil($perangkat),
            'PinOffline' => [
                'Tersedia' => $perangkat->KunciPinOffline !== null,
                'Parameter' => VerifierPinOffline::AmbilParameter(),
                'BatasSalah' => 5,
                'MenitKunci' => 5,
            ],
        ];
    }
}
