<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Pelanggan\Enum\JenisMutasiSesi;
use App\Domain\Pelanggan\Enum\StatusSaldoSesi;
use App\Domain\Pelanggan\Enum\SumberMutasiSesi;
use App\Domain\Pelanggan\Layanan\BukuSesi;
use App\Domain\Pelanggan\Layanan\PenutupSisaSesi;
use App\Domain\Pelanggan\Model\SaldoSesi;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Menghanguskan paket sesi yang masa berlakunya lewat (F-16d bagian 2) untuk tenant aktif: saldo `Aktif` dengan
 * `BerlakuSampai` sebelum [hariIni] ditutup `Hangus` (sumber `Jadwal`, idempoten per saldo), sisa nilainya diakui
 * sebagai Pendapatan Lain. Hasil: jumlah saldo yang dihanguskan.
 */
final class HanguskanSesiKedaluwarsa
{
    public function __construct(
        private readonly BukuSesi $buku,
        private readonly PenutupSisaSesi $penutup,
    ) {}

    public function Jalankan(CarbonImmutable $hariIni): int
    {
        $total = 0;

        SaldoSesi::query()
            ->where('Status', StatusSaldoSesi::Aktif->value)
            ->whereNotNull('BerlakuSampai')
            ->where('BerlakuSampai', '<', $hariIni->toDateString())
            ->orderBy('Id')
            ->pluck('Id')
            ->each(function (int $id) use ($hariIni, &$total): void {
                DB::transaction(function () use ($id, $hariIni, &$total): void {
                    $saldo = $this->buku->Kunci($id);

                    if ($saldo === null || $saldo->Status !== StatusSaldoSesi::Aktif) {
                        return;
                    }

                    $this->penutup->Tutup($saldo, JenisMutasiSesi::Hangus, SumberMutasiSesi::Jadwal, $hariIni, 'Masa berlaku berakhir '.$saldo->BerlakuSampai?->toDateString(), null);
                    $total++;
                });
            });

        return $total;
    }
}
