<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pemenuhan\Enum\StatusReservasi;
use App\Domain\Pemenuhan\Model\Reservasi;

/**
 * F-07 mode service: pelanggan membatalkan reservasinya sendiri lewat halaman publik (kode akses) selama belum datang
 * dan jam mulai belum lewat. Alasan tercatat "Dibatalkan pelanggan".
 */
final class BatalkanReservasiPelanggan
{
    public function __construct(private readonly UbahStatusReservasi $ubah) {}

    public function Jalankan(Reservasi $reservasi): Reservasi
    {
        if (! in_array($reservasi->Status, [StatusReservasi::Menunggu, StatusReservasi::Dikonfirmasi], true) || ! $reservasi->MulaiPada->isFuture()) {
            throw new PelanggaranAturanBisnis('TidakBisaDibatalkan', 'Reservasi ini sudah tidak bisa dibatalkan. Hubungi toko.', 'Umum', 409);
        }

        return $this->ubah->Jalankan($reservasi, StatusReservasi::Batal, null, 'Dibatalkan pelanggan');
    }
}
