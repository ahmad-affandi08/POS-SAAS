<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Tenant\Data\SumberFitur;
use App\Domain\Tenant\Model\Langganan;

/**
 * Merakit masukan `EvaluatorFitur` dari langganan tenant (BR-P04.3, BR-P04.7). Saat ini: fitur & batas paket
 * langganan. Add-on aktif (`LanggananAddon`, F-19), override pengelola (P-07), flag fitur (P-10), dan modul outlet
 * (`OutletFitur`, F-01) ditambahkan ke sini bersama flow masing-masing.
 */
final class SumberFiturTenant
{
    /**
     * @param  bool  $kunci  kunci baris langganan (FOR UPDATE) agar penambahan outlet/pengguna bersamaan dari satu
     *                       tenant diproses berurutan; hanya di dalam transaksi.
     */
    public function Ambil(int $idTenant, bool $kunci = false): ?SumberFitur
    {
        $langganan = Langganan::query()
            ->where('IdTenant', $idTenant)
            ->when($kunci, fn ($kueri) => $kueri->lockForUpdate())
            ->with('Paket.Fitur')
            ->first();

        if ($langganan === null) {
            return null;
        }

        return new SumberFitur(
            fiturPaket: $langganan->Paket->AmbilKunciFitur(),
            batasPaket: $langganan->Paket->AmbilBatas(),
        );
    }

    public function AmbilNamaPaket(int $idTenant): ?string
    {
        $langganan = Langganan::query()->where('IdTenant', $idTenant)->with('Paket')->first();

        return $langganan?->Paket->Nama;
    }
}
