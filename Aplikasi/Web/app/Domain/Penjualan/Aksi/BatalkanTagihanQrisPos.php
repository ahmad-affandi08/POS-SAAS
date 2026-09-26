<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Penjualan\Enum\StatusTagihanQris;
use App\Domain\Penjualan\Layanan\PencariTagihanQris;
use App\Domain\Penjualan\Model\TagihanQris;
use Illuminate\Support\Facades\DB;

/**
 * F-08: kasir membatalkan tagihan QRIS yang belum dibayar (pelanggan ganti metode). Sebelum batal, gerbang ditanya
 * sekali (tanpa jeda) agar pembayaran yang baru masuk tidak ikut dibatalkan. `Lunas` = 409 `SudahLunas` (perangkat
 * wajib memakai pembayaran itu). Status akhir lain (sudah `Dibatalkan`/`Kedaluwarsa`/`Gagal`) dikembalikan apa adanya
 * (idempoten). Tagihan di gerbang tidak dibatalkan (port gerbang belum punya pembatalan); kedaluwarsa sendiri, dan
 * bila tetap dibayar, webhook mencatatnya `Lunas` untuk ditelusuri.
 */
final class BatalkanTagihanQrisPos
{
    public function __construct(
        private readonly PencariTagihanQris $pencari,
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(string $uuid, int $idOutlet): TagihanQris
    {
        $tagihan = $this->pencari->CekKeGerbang($this->pencari->CariDiOutlet($uuid, $idOutlet), paksa: true);

        return DB::transaction(function () use ($tagihan): TagihanQris {
            $tagihan = TagihanQris::query()->whereKey($tagihan->Id)->lockForUpdate()->firstOrFail();

            if ($tagihan->Status === StatusTagihanQris::Lunas) {
                throw new PelanggaranAturanBisnis('SudahLunas', 'Tagihan QRIS ini sudah dibayar. Pakai pembayaran ini untuk menyelesaikan transaksi.', 'Uuid', 409);
            }

            if (! $tagihan->Status->BisaBerubahKe(StatusTagihanQris::Dibatalkan)) {
                return $tagihan;
            }

            $tagihan->Status = StatusTagihanQris::Dibatalkan;
            $tagihan->save();
            $this->riwayat->Catat(TagihanQris::JENIS_DOKUMEN, $tagihan->Id, StatusTagihanQris::Menunggu->value, StatusTagihanQris::Dibatalkan->value, null, 'Dibatalkan kasir');
            $this->audit->Catat('tagihan-qris.batal', $tagihan, ['Status' => StatusTagihanQris::Menunggu->value], ['Status' => StatusTagihanQris::Dibatalkan->value]);

            return $tagihan;
        });
    }
}
