<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Sinkron\Layanan;

/**
 * Menandai bahwa proses sedang menerima item sinkron dari aplikasi kasir (scoped per request). Dipakai aturan yang
 * berbeda untuk transaksi yang sudah terjadi offline, misal periode terkunci F-15/§18: transaksi POS tetap diterima
 * dan dibukukan di periode terbuka berikutnya, sedangkan transaksi back-office ditolak.
 */
final class PenandaSinkronPos
{
    private int $kedalaman = 0;

    /**
     * @template T
     *
     * @param  callable(): T  $proses
     * @return T
     */
    public function Jalankan(callable $proses): mixed
    {
        $this->kedalaman++;

        try {
            return $proses();
        } finally {
            $this->kedalaman--;
        }
    }

    public function CekAktif(): bool
    {
        return $this->kedalaman > 0;
    }
}
