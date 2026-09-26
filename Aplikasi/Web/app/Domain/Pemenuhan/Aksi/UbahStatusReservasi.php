<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pemenuhan\Enum\StatusReservasi;
use App\Domain\Pemenuhan\Model\Reservasi;
use Illuminate\Support\Facades\DB;

/**
 * F-07 mode service: konfirmasi, check-in (`Hadir`), selesai, batal (alasan wajib bila oleh toko), atau tandai tidak
 * datang (hanya setelah jam mulai lewat). Transisi mengikuti `StatusReservasi::BisaBerubahKe`, dicatat di
 * `RiwayatStatusDokumen` dan audit (bila oleh pengguna).
 */
final class UbahStatusReservasi
{
    public function __construct(
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(Reservasi $reservasi, StatusReservasi $tujuan, ?int $idPengguna, ?string $alasan = null): Reservasi
    {
        return DB::transaction(function () use ($reservasi, $tujuan, $idPengguna, $alasan): Reservasi {
            $r = Reservasi::query()->whereKey($reservasi->Id)->lockForUpdate()->firstOrFail();
            $alasan = $alasan === null ? null : mb_substr(trim($alasan), 0, 255);

            if (! $r->Status->BisaBerubahKe($tujuan)) {
                throw new PelanggaranAturanBisnis('StatusTidakBisaDiubah', "Reservasi berstatus {$r->Status->AmbilLabel()} tidak bisa diubah menjadi {$tujuan->AmbilLabel()}.", 'Status', 409);
            }

            if ($tujuan === StatusReservasi::TidakDatang && $r->MulaiPada->isFuture()) {
                throw new PelanggaranAturanBisnis('BelumWaktunya', 'Tandai tidak datang setelah jam reservasi lewat.', 'Status', 409);
            }

            if ($tujuan === StatusReservasi::Batal && $idPengguna !== null && ($alasan === null || $alasan === '')) {
                throw new PelanggaranAturanBisnis('AlasanWajib', 'Tulis alasan pembatalan.', 'Alasan');
            }

            $dari = $r->Status;
            $r->UbahStatus($tujuan);

            if ($tujuan === StatusReservasi::Hadir) {
                $r->HadirPada = now();
            }

            if ($tujuan === StatusReservasi::Batal) {
                $r->AlasanBatal = $alasan === '' ? null : $alasan;
            }

            $r->save();
            $this->riwayat->Catat(Reservasi::JENIS_DOKUMEN, $r->Id, $dari->value, $tujuan->value, $idPengguna, $alasan === '' ? null : $alasan);

            if ($idPengguna !== null) {
                $this->audit->Catat('reservasi.status', $r, ['Status' => $dari->value], ['Status' => $tujuan->value, 'Alasan' => $alasan], idPengguna: $idPengguna);
            }

            return $r;
        });
    }
}
