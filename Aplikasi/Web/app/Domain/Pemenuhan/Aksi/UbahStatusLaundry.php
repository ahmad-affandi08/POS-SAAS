<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pemenuhan\Enum\StatusLaundry;
use App\Domain\Pemenuhan\Kueri\PengaturanLaundryTenant;
use App\Domain\Pemenuhan\Model\TiketLaundry;
use App\Domain\Pemenuhan\Tugas\KirimNotifikasiLaundrySiapTugas;
use Illuminate\Support\Facades\DB;

/**
 * Ubah status proses tiket laundry (§9.9, F-10) dari back-office atau aplikasi kasir: maju ke tahap berikutnya (boleh
 * melompat), `Siap` mencatat `SiapPada` dan mengantrekan notifikasi WhatsApp "siap diambil" (bila diaktifkan dan
 * nomor ada) setelah transaksi tersimpan, `Diambil` (hanya dari Siap) mencatat waktu & pengguna. Status sama =
 * idempoten. `Dibatalkan` hanya lewat void penjualan. Riwayat status & audit dicatat.
 */
final class UbahStatusLaundry
{
    public function __construct(
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly PencatatAudit $audit,
        private readonly PengaturanLaundryTenant $pengaturan,
    ) {}

    public function Jalankan(TiketLaundry $tiket, StatusLaundry $tujuan, int $idPengguna): TiketLaundry
    {
        if ($tujuan === StatusLaundry::Dibatalkan) {
            throw new PelanggaranAturanBisnis('StatusTidakBisaDiubah', 'Batalkan tiket laundry dengan void penjualannya.', 'Status', 409);
        }

        return DB::transaction(function () use ($tiket, $tujuan, $idPengguna): TiketLaundry {
            $t = TiketLaundry::query()->whereKey($tiket->Id)->lockForUpdate()->firstOrFail();

            if ($t->Status === $tujuan) {
                return $t;
            }

            if (! $t->Status->BisaBerubahKe($tujuan)) {
                throw new PelanggaranAturanBisnis('StatusTidakBisaDiubah', "Cucian berstatus {$t->Status->AmbilLabel()} tidak bisa diubah menjadi {$tujuan->AmbilLabel()}.", 'Status', 409);
            }

            $dari = $t->Status;
            $t->UbahStatus($tujuan);

            if ($tujuan === StatusLaundry::Siap) {
                $t->SiapPada = now();
            }

            if ($tujuan === StatusLaundry::Diambil) {
                $t->DiambilPada = now();
                $t->DiambilOleh = $idPengguna;
            }

            $t->save();
            $this->riwayat->Catat(TiketLaundry::JENIS_DOKUMEN, $t->Id, $dari->value, $tujuan->value, $idPengguna);
            $this->audit->Catat('laundry.status', $t, ['Status' => $dari->value], ['Status' => $tujuan->value], idPengguna: $idPengguna);

            if ($tujuan === StatusLaundry::Siap && $t->NoHp !== null && $t->NotifikasiSiapPada === null && $this->pengaturan->Ambil()->NotifikasiSiap) {
                KirimNotifikasiLaundrySiapTugas::dispatch($t->IdTenant, $t->Id)->afterCommit();
            }

            return $t;
        });
    }
}
