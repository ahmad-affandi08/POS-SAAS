<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pemenuhan\Enum\StatusTiketDapur;
use App\Domain\Pemenuhan\Model\TiketDapur;
use Illuminate\Support\Facades\DB;

/**
 * F-10b: KDS mengubah status tiket satu langkah maju (atau mundur satu langkah untuk koreksi). Waktu mulai, siap, dan
 * disajikan dicatat dari jam server (dasar laporan waktu saji). Mundur mengosongkan waktu langkah yang dibatalkan.
 */
final class UbahStatusTiketDapur
{
    public function Jalankan(TiketDapur $tiket, StatusTiketDapur $tujuan): TiketDapur
    {
        return DB::transaction(function () use ($tiket, $tujuan): TiketDapur {
            $tiket = TiketDapur::query()->lockForUpdate()->findOrFail($tiket->Id);
            $asal = $tiket->Status;

            if ($asal === $tujuan) {
                return $tiket;
            }

            if (! $asal->BisaBerubahKe($tujuan)) {
                throw new PelanggaranAturanBisnis('StatusTiketTidakValid', "Tiket berstatus {$asal->AmbilLabel()} tidak bisa langsung menjadi {$tujuan->AmbilLabel()}.", 'Status');
            }

            $isian = ['Status' => $tujuan];
            // Maju: catat waktu langkah tujuan. Mundur (koreksi): kosongkan waktu langkah yang dibatalkan.
            $kolom = $tujuan->CekLebihLanjutDari($asal) ? $tujuan->AmbilKolomWaktu() : $asal->AmbilKolomWaktu();

            if ($kolom !== null) {
                $isian[$kolom] = $tujuan->CekLebihLanjutDari($asal) ? now() : null;
            }

            $tiket->update($isian);

            return $tiket;
        });
    }
}
