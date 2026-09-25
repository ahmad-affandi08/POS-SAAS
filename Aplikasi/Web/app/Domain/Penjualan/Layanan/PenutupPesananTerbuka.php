<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Penjualan\Enum\KodeAlasanTinjauan;
use App\Domain\Penjualan\Enum\StatusPesananTerbuka;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PesananTerbuka;

/**
 * Penutupan pesanan terbuka oleh pembayarannya (`Penjualan.Buat` + `UuidPesananTerbuka`, F-07 mode meja fase 1).
 * Uang yang sudah diterima tidak pernah ditolak karena pesanan (§18.3): pesanan tidak dikenal → tinjauan
 * `PesananTidakDikenal`; pesanan sudah dibayar/dibatalkan (bayar ganda offline) → tinjauan `PesananDibayarGanda`.
 */
final class PenutupPesananTerbuka
{
    /**
     * @return array{0: PesananTerbuka|null, 1: array<string, string>} pesanan (dikunci) dan alasan tinjauan
     */
    public function Cari(?string $uuidPesanan, int $idOutlet): array
    {
        if ($uuidPesanan === null) {
            return [null, []];
        }

        $pesanan = PesananTerbuka::query()->where('Uuid', $uuidPesanan)->lockForUpdate()->first();

        if ($pesanan === null || $pesanan->IdOutlet !== $idOutlet) {
            return [null, [KodeAlasanTinjauan::PesananTidakDikenal->value => KodeAlasanTinjauan::PesananTidakDikenal->value.': pesanan terbuka belum diterima server saat penjualan diterima']];
        }

        if ($pesanan->Status !== StatusPesananTerbuka::Terbuka) {
            return [$pesanan, [KodeAlasanTinjauan::PesananDibayarGanda->value => KodeAlasanTinjauan::PesananDibayarGanda->value.": pesanan {$pesanan->Nomor} sudah {$pesanan->Status->AmbilLabel()} sebelum penjualan ini diterima"]];
        }

        return [$pesanan, []];
    }

    public function Tutup(?PesananTerbuka $pesanan, Penjualan $penjualan): void
    {
        if ($pesanan === null || $pesanan->Status !== StatusPesananTerbuka::Terbuka) {
            return;
        }

        $pesanan->update([
            'Status' => StatusPesananTerbuka::Dibayar,
            'IdPenjualan' => $penjualan->Id,
            'DitutupPada' => $penjualan->DibuatOfflinePada,
            'IdPerangkatKunciBayar' => null,
            'KunciBayarSampai' => null,
        ]);
    }
}
