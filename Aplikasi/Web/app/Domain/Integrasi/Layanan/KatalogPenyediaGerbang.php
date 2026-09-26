<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Layanan;

use App\Domain\Integrasi\Enum\PenyediaGerbang;
use App\Domain\Integrasi\Model\KatalogGerbangPembayaran;

/**
 * Membaca katalog platform penyedia gerbang pembayaran yang diizinkan untuk tenant (P-05 v2.06). Penyedia tanpa baris
 * katalog = diizinkan. Dipakai back-office tenant (pilihan penyedia) dan runtime (penyedia yang dilarang tidak bisa
 * membuat tagihan baru).
 */
final class KatalogPenyediaGerbang
{
    /**
     * @return array<string, bool> Kode penyedia => diizinkan, untuk semua penyedia katalog.
     */
    public function AmbilStatus(): array
    {
        $tersimpan = KatalogGerbangPembayaran::query()->get(['Penyedia', 'Diizinkan'])
            ->mapWithKeys(fn (KatalogGerbangPembayaran $baris): array => [$baris->Penyedia->value => $baris->Diizinkan])
            ->all();
        $hasil = [];

        foreach (PenyediaGerbang::cases() as $penyedia) {
            $hasil[$penyedia->value] = $tersimpan[$penyedia->value] ?? true;
        }

        return $hasil;
    }

    /**
     * @return list<PenyediaGerbang>
     */
    public function AmbilDiizinkan(): array
    {
        $status = $this->AmbilStatus();

        return array_values(array_filter(PenyediaGerbang::cases(), fn (PenyediaGerbang $p): bool => $status[$p->value]));
    }

    public function CekDiizinkan(PenyediaGerbang $penyedia): bool
    {
        return KatalogGerbangPembayaran::query()->where('Penyedia', $penyedia->value)->where('Diizinkan', false)->doesntExist();
    }
}
