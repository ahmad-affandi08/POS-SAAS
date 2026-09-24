<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Kueri;

use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Katalog\Model\Satuan;

/**
 * Satuan tenant aktif untuk pilihan form (tipe FE `OpsiSatuan`) dan halaman satuan (F-03 E.5), urut nama.
 */
final class DaftarSatuan
{
    /**
     * @return list<array{Uuid: string, Nama: string, Simbol: string, BolehDesimal: bool}>
     */
    public function AmbilOpsi(): array
    {
        return array_values(Satuan::query()->orderBy('Nama')->get()
            ->map(fn (Satuan $satuan): array => ['Uuid' => $satuan->Uuid, 'Nama' => $satuan->Nama, 'Simbol' => $satuan->Simbol, 'BolehDesimal' => $satuan->BolehDesimal])
            ->all());
    }

    /**
     * @return list<array{Uuid: string, Nama: string, Simbol: string, BolehDesimal: bool, KodeStandar: string|null, JumlahProduk: int}>
     */
    public function AmbilUntukHalaman(): array
    {
        $jumlah = ProdukSatuan::query()->whereHas('Produk')
            ->selectRaw('IdSatuan, count(distinct IdProduk) as Jumlah')->groupBy('IdSatuan')->pluck('Jumlah', 'IdSatuan');

        return array_values(Satuan::query()->orderBy('Nama')->get()
            ->map(fn (Satuan $satuan): array => [
                'Uuid' => $satuan->Uuid,
                'Nama' => $satuan->Nama,
                'Simbol' => $satuan->Simbol,
                'BolehDesimal' => $satuan->BolehDesimal,
                'KodeStandar' => $satuan->KodeStandar,
                'JumlahProduk' => (int) ($jumlah->get($satuan->Id) ?? 0),
            ])
            ->all());
    }

    /**
     * @return array{Uuid: string, Nama: string, Simbol: string, BolehDesimal: bool}
     */
    public static function PetakanOpsi(Satuan $satuan): array
    {
        return ['Uuid' => $satuan->Uuid, 'Nama' => $satuan->Nama, 'Simbol' => $satuan->Simbol, 'BolehDesimal' => $satuan->BolehDesimal];
    }
}
