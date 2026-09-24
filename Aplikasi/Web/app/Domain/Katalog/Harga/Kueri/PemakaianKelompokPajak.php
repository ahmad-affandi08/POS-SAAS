<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Kueri;

use App\Domain\Katalog\Model\Produk;

/**
 * Jumlah produk (belum dihapus) per kelompok pajak untuk halaman kelompok pajak F-03 (E.8). Kueri milik Katalog
 * karena membaca tabel `Produk`; domain Pajak menerima hasilnya sebagai peta.
 */
final class PemakaianKelompokPajak
{
    /**
     * @return array<int, int> `[IdKelompokPajak => jumlah produk]`
     */
    public function HitungPerKelompok(): array
    {
        $hasil = [];

        foreach (Produk::query()->whereNotNull('IdKelompokPajak')->selectRaw('IdKelompokPajak, count(*) as Jumlah')->groupBy('IdKelompokPajak')->toBase()->get() as $baris) {
            $hasil[(int) $baris->IdKelompokPajak] = (int) $baris->Jumlah;
        }

        return $hasil;
    }
}
