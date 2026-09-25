<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Layanan;

use App\Domain\Akuntansi\Data\DataAkun;
use App\Domain\Akuntansi\Enum\TipeAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;

/**
 * Aturan isian akun bersama `TambahAkun` & `UbahAkun` (F-13a): digit pertama kode = tipe (§11.2), kode unik per
 * tenant, dan penanda kas/bank hanya untuk aset non-kontra.
 */
final class AturanAkun
{
    /**
     * @throws PelanggaranAturanBisnis KodeAkunTidakSesuaiTipe, KodeAkunSudahAda, KasBankHanyaAset
     */
    public static function Periksa(DataAkun $data, ?int $kecualiId = null): void
    {
        if (! str_starts_with($data->kode, $data->tipe->AmbilDigitAwalKode().'-')) {
            throw new PelanggaranAturanBisnis(
                'KodeAkunTidakSesuaiTipe',
                "Kode akun {$data->tipe->AmbilLabel()} harus diawali {$data->tipe->AmbilDigitAwalKode()}- (misal {$data->tipe->AmbilDigitAwalKode()}-1100).",
                'Kode',
            );
        }

        $ada = Akun::query()->where('Kode', $data->kode)->when($kecualiId !== null, fn ($k) => $k->whereKeyNot($kecualiId))->first(['Nama']);

        if ($ada instanceof Akun) {
            throw new PelanggaranAturanBisnis('KodeAkunSudahAda', "Kode {$data->kode} sudah dipakai akun \"{$ada->Nama}\". Pakai kode lain.", 'Kode');
        }

        if ($data->kasBank && ($data->tipe !== TipeAkun::Aset || $data->kontra)) {
            throw new PelanggaranAturanBisnis('KasBankHanyaAset', 'Hanya akun aset (bukan akun kontra) yang bisa ditandai sebagai akun kas/bank.', 'KasBank');
        }
    }
}
