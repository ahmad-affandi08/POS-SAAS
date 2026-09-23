<?php

declare(strict_types=1);

namespace App\Domain\Referensi\Kueri;

use App\Domain\Referensi\Model\SatuanStandar;

/**
 * Satuan standar aktif (P-02) yang disalin ke `Satuan` tenant oleh template sektor (F-01). Kode tidak aktif atau
 * tidak dikenal tidak ikut.
 */
final class SatuanStandarAktif
{
    /**
     * @param  list<string>  $kode
     * @return list<array{Kode: string, Nama: string, Simbol: string, BolehDesimal: bool}>
     */
    public function AmbilBerdasarkanKode(array $kode): array
    {
        if ($kode === []) {
            return [];
        }

        $baris = SatuanStandar::query()->whereIn('Kode', $kode)->where('Aktif', true)->get()->keyBy('Kode');
        $hasil = [];

        // Urutan mengikuti urutan kode di template.
        foreach (array_values(array_unique($kode)) as $satu) {
            $satuan = $baris->get($satu);

            if ($satuan instanceof SatuanStandar) {
                $hasil[] = ['Kode' => $satuan->Kode, 'Nama' => $satuan->Nama, 'Simbol' => $satuan->Simbol, 'BolehDesimal' => $satuan->BolehDesimal];
            }
        }

        return $hasil;
    }
}
