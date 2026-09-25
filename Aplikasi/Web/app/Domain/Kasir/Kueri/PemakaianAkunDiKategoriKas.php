<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Kueri;

use App\Domain\Akuntansi\Kontrak\PemeriksaPemakaianAkun;
use App\Domain\Kasir\Model\KategoriKas;

/**
 * F-13a: akun yang dirujuk kategori kas (F-06, aktif maupun nonaktif) tidak bisa dihapus dari bagan akun.
 */
final class PemakaianAkunDiKategoriKas implements PemeriksaPemakaianAkun
{
    public function PeriksaPemakaian(int $idAkun): ?string
    {
        $nama = KategoriKas::query()->where('IdAkun', $idAkun)->orderBy('Nama')->value('Nama');

        return is_string($nama) ? "dipakai kategori kas \"{$nama}\"" : null;
    }
}
