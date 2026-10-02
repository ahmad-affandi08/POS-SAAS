<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Penjualan\Enum\ModeKasir;
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

    /**
     * Mode kasir dari template sektor outlet (`pos.retail` → `Konfigurasi.ModeKasir`), kosong bila belum ada.
     *
     * @return list<string>
     */
    public function AmbilModeKasir(int $idOutlet): array
    {
        $mode = $this->AmbilKonfigurasi($idOutlet, OutletFitur::KUNCI_POS)['ModeKasir'] ?? [];

        return is_array($mode) ? array_values(array_filter($mode, 'is_string')) : [];
    }

    /**
     * K-8: mode kasir yang dikenal (`ModeKasir`) dan bawaannya untuk perangkat kasir. Bawaan = `ModeKasirDefault`
     * template bila termasuk daftar, selain itu mode pertama; null bila outlet belum punya mode kasir.
     *
     * @return array{ModeKasir: list<string>, ModeKasirBawaan: string|null}
     */
    public function AmbilModeKasirPos(int $idOutlet): array
    {
        $konfigurasi = $this->AmbilKonfigurasi($idOutlet, OutletFitur::KUNCI_POS) ?? [];
        $dikenal = array_map(fn (ModeKasir $mode) => $mode->value, ModeKasir::cases());
        $mode = array_values(array_unique(array_intersect($this->AmbilModeKasir($idOutlet), $dikenal)));
        $bawaan = $konfigurasi['ModeKasirDefault'] ?? null;

        return [
            'ModeKasir' => $mode,
            'ModeKasirBawaan' => is_string($bawaan) && in_array($bawaan, $mode, true) ? $bawaan : ($mode[0] ?? null),
        ];
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
