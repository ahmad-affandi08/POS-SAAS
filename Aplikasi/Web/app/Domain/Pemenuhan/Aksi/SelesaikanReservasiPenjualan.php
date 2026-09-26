<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Aksi;

use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Pemenuhan\Enum\StatusReservasi;
use App\Domain\Pemenuhan\Model\Reservasi;

/**
 * F-07 mode service bagian 2: penjualan kasir yang merujuk reservasi (`Penjualan.Buat` `UuidReservasi`) menautkan
 * `IdPenjualan` dan menyelesaikan reservasi di transaksi yang sama. Reservasi yang belum check-in dicatat Hadir lalu
 * Selesai. Reservasi tidak dikenal, di outlet lain, sudah selesai/batal, atau sudah tertaut = diabaikan dan dilaporkan
 * sebagai masalah (penjualan tetap diterima; aplikasi offline tidak boleh gagal karena reservasi).
 *
 * @return list<string> masalah untuk tinjauan penjualan
 */
final class SelesaikanReservasiPenjualan
{
    public function __construct(private readonly PencatatRiwayatStatus $riwayat) {}

    /**
     * @return list<string>
     */
    public function Jalankan(string $uuidReservasi, int $idPenjualan, int $idOutlet, ?int $idPengguna): array
    {
        $r = Reservasi::query()->where('Uuid', $uuidReservasi)->lockForUpdate()->first();

        if ($r === null || $r->IdOutlet !== $idOutlet) {
            return ['reservasi tidak ditemukan di outlet ini'];
        }

        if ($r->IdPenjualan === $idPenjualan) {
            return [];
        }

        if ($r->IdPenjualan !== null || ! in_array($r->Status, [StatusReservasi::Menunggu, StatusReservasi::Dikonfirmasi, StatusReservasi::Hadir], true)) {
            return ["reservasi {$r->Nomor} berstatus {$r->Status->AmbilLabel()}"];
        }

        if ($r->Status !== StatusReservasi::Hadir) {
            $dari = $r->Status;
            $r->UbahStatus(StatusReservasi::Hadir);
            $r->HadirPada ??= now();
            $this->riwayat->Catat(Reservasi::JENIS_DOKUMEN, $r->Id, $dari->value, StatusReservasi::Hadir->value, $idPengguna);
        }

        $r->UbahStatus(StatusReservasi::Selesai);
        $r->IdPenjualan = $idPenjualan;
        $r->save();
        $this->riwayat->Catat(Reservasi::JENIS_DOKUMEN, $r->Id, StatusReservasi::Hadir->value, StatusReservasi::Selesai->value, $idPengguna);

        return [];
    }
}
