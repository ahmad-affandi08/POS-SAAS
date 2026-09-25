<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Akuntansi\Kontrak\PemeriksaPemakaianAkun;
use App\Domain\Penjualan\Model\MetodePembayaran;

/**
 * F-13a: akun yang dirujuk metode pembayaran (akun penerima atau akun kliring) tidak bisa dihapus dari bagan akun.
 */
final class PemakaianAkunDiMetodePembayaran implements PemeriksaPemakaianAkun
{
    public function PeriksaPemakaian(int $idAkun): ?string
    {
        $nama = MetodePembayaran::query()
            ->where(fn ($kueri) => $kueri->where('IdAkun', $idAkun)->orWhere('IdAkunKliring', $idAkun))
            ->orderBy('Nama')
            ->value('Nama');

        return is_string($nama) ? "dipakai metode pembayaran \"{$nama}\"" : null;
    }
}
