<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Tenant\Model\Fitur;
use App\Domain\Tenant\Model\OutletFitur;

/**
 * Modul yang diaktifkan template per outlet (F-01, BR-01.3) dan nama fitur katalog untuk tampilan.
 */
final class FiturOutlet
{
    /**
     * Kunci fitur aktif outlet di tenant aktif.
     *
     * @return list<string>
     */
    public function AmbilKunci(int $idOutlet): array
    {
        return array_values(array_map('strval', OutletFitur::query()
            ->where('IdOutlet', $idOutlet)
            ->where('Aktif', true)
            ->orderBy('KunciFitur')
            ->pluck('KunciFitur')
            ->all()));
    }

    /**
     * Masukan `modulOutletAktif` EvaluatorFitur: null bila outlet belum punya baris sama sekali (tanpa batasan).
     * Hanya dipanggil dengan konteks tenant aktif (scope `MilikTenant`); tenant disebut ulang sebagai penjaga.
     *
     * @return list<string>|null
     */
    public function AmbilKunciAktifAtauNull(int $idTenant, int $idOutlet): ?array
    {
        $baris = OutletFitur::query()
            ->where('IdTenant', $idTenant)
            ->where('IdOutlet', $idOutlet)
            ->get(['KunciFitur', 'Aktif']);

        if ($baris->isEmpty()) {
            return null;
        }

        return array_values(array_map('strval', $baris->where('Aktif', true)->pluck('KunciFitur')->all()));
    }

    /** @return array<string, mixed>|null */
    public function AmbilKonfigurasi(int $idOutlet, string $kunciFitur): ?array
    {
        return OutletFitur::query()->where('IdOutlet', $idOutlet)->where('KunciFitur', $kunciFitur)->first()?->Konfigurasi;
    }

    /**
     * Nama fitur katalog (P-04) per kunci; kunci yang tidak ada di katalog tidak ikut.
     *
     * @param  list<string>  $kunci
     * @return array<string, string>
     */
    public function AmbilNamaFitur(array $kunci): array
    {
        if ($kunci === []) {
            return [];
        }

        /** @var array<string, string> */
        return Fitur::query()->whereIn('Kunci', $kunci)->pluck('Nama', 'Kunci')->all();
    }
}
