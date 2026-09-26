<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Penjualan\Enum\StatusPesananSendiri;
use App\Domain\Penjualan\Model\PesananSendiri;

/**
 * F-17: pemeriksaan bersama terima/tolak pesanan QR di POS: pesanan di outlet perangkat (dikunci baris), belum
 * kedaluwarsa (409 `Kedaluwarsa`), dan belum diproses dengan hasil lain (409 `SudahDiproses`). Pelaku diperiksa
 * `PenjagaPesananTerbuka::CariPelaku` (izin `penjualan.buat` atau `pesanan.meja.catat`).
 */
final class PenjagaPesananSendiri
{
    public function CariUntukDiproses(string $uuid, int $idOutlet): PesananSendiri
    {
        $pesanan = PesananSendiri::query()->where('Uuid', $uuid)->lockForUpdate()->first();

        if ($pesanan === null || $pesanan->IdOutlet !== $idOutlet) {
            throw new PelanggaranAturanBisnis('PesananTidakDitemukan', 'Pesanan QR tidak ditemukan di outlet ini.', 'Uuid', 404);
        }

        return $pesanan;
    }

    /** Pesanan masih menunggu dan belum lewat 30 menit; selain itu galat 409 dengan status terkini. */
    public function PastikanMenunggu(PesananSendiri $pesanan): void
    {
        if ($pesanan->Status === StatusPesananSendiri::Kedaluwarsa || PenandaKedaluwarsaPesananSendiri::CekKedaluwarsa($pesanan)) {
            throw new PelanggaranAturanBisnis('Kedaluwarsa', "Pesanan {$pesanan->Nomor} sudah kedaluwarsa (tidak diproses ".PenandaKedaluwarsaPesananSendiri::MENIT_BERLAKU.' menit). Minta tamu memesan ulang.', 'Uuid', 409, ['Status' => StatusPesananSendiri::Kedaluwarsa->value]);
        }

        if (! $pesanan->Status->BisaBerubahKe(StatusPesananSendiri::Diterima)) {
            throw new PelanggaranAturanBisnis('SudahDiproses', "Pesanan {$pesanan->Nomor} sudah {$pesanan->Status->AmbilLabel()} di perangkat lain.", 'Uuid', 409, ['Status' => $pesanan->Status->value]);
        }
    }
}
