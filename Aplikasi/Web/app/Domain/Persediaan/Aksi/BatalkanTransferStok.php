<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Persediaan\Enum\StatusTransferStok;
use App\Domain\Persediaan\Model\TransferStok;
use Illuminate\Support\Facades\DB;

/**
 * Membatalkan draf transfer stok sebelum dikirim (F-05b): tanpa mutasi atau jurnal; dokumen tetap tersimpan
 * berstatus Dibatalkan dengan alasan. Transfer yang sudah dikirim tidak bisa dibatalkan (koreksi = transfer balik).
 */
final class BatalkanTransferStok
{
    public function __construct(
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(TransferStok $transfer, string $alasan, int $idPengguna): TransferStok
    {
        return DB::transaction(function () use ($transfer, $alasan, $idPengguna): TransferStok {
            $transfer = TransferStok::query()->whereKey($transfer->Id)->lockForUpdate()->firstOrFail();

            if ($transfer->Status === StatusTransferStok::Dibatalkan) {
                return $transfer;
            }

            if ($transfer->Status !== StatusTransferStok::Draf) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', 'Transfer yang sudah dikirim tidak bisa dibatalkan. Buat transfer balik bila perlu.');
            }

            $transfer->UbahStatus(StatusTransferStok::Dibatalkan);
            $transfer->fill(['AlasanBatal' => mb_substr(trim($alasan), 0, 255), 'DibatalkanOleh' => $idPengguna, 'DibatalkanPada' => now(), 'DiubahOleh' => $idPengguna]);
            $transfer->save();
            $this->riwayat->Catat(TransferStok::JENIS_DOKUMEN, $transfer->Id, StatusTransferStok::Draf->value, StatusTransferStok::Dibatalkan->value, $idPengguna, $transfer->AlasanBatal);
            $this->audit->Catat('transfer-stok.batal', $transfer, ['Status' => StatusTransferStok::Draf->value], ['Status' => StatusTransferStok::Dibatalkan->value, 'AlasanBatal' => $transfer->AlasanBatal], idPengguna: $idPengguna);

            return $transfer;
        });
    }
}
