<?php

declare(strict_types=1);

namespace App\Domain\Referensi\Kueri;

use App\Domain\Referensi\Enum\TingkatWilayah;
use App\Domain\Referensi\Model\Wilayah;

/**
 * Kabupaten/kota resmi (P-02) untuk profil outlet: kota menentukan zona waktu outlet dan PBJT yang berlaku.
 */
final class WilayahKota
{
    /**
     * @return array{Kode: string, Nama: string, NamaProvinsi: string|null, ZonaWaktu: string, ZonaWaktuIana: string}|null
     */
    public function Cari(string $kode): ?array
    {
        $kota = Wilayah::query()->where('Kode', $kode)->where('Tingkat', TingkatWilayah::KabupatenKota->value)->first();

        return $kota === null ? null : $this->Petakan($kota, $this->AmbilNamaProvinsi([$kota->KodeInduk])[$kota->KodeInduk] ?? null);
    }

    /**
     * @return list<array{Kode: string, Nama: string, NamaProvinsi: string|null, ZonaWaktu: string, ZonaWaktuIana: string}>
     */
    public function AmbilSemua(): array
    {
        $kota = Wilayah::query()->where('Tingkat', TingkatWilayah::KabupatenKota->value)->orderBy('Kode')->get();
        $provinsi = $this->AmbilNamaProvinsi(array_values(array_unique(array_filter($kota->map(fn (Wilayah $baris) => $baris->KodeInduk)->all()))));

        return array_values($kota->map(fn (Wilayah $baris) => $this->Petakan($baris, $provinsi[$baris->KodeInduk] ?? null))->all());
    }

    /**
     * @param  list<string|null>  $kode
     * @return array<string, string>
     */
    private function AmbilNamaProvinsi(array $kode): array
    {
        /** @var array<string, string> */
        return Wilayah::query()->whereIn('Kode', array_filter($kode))->pluck('Nama', 'Kode')->all();
    }

    /**
     * @return array{Kode: string, Nama: string, NamaProvinsi: string|null, ZonaWaktu: string, ZonaWaktuIana: string}
     */
    private function Petakan(Wilayah $kota, ?string $namaProvinsi): array
    {
        return [
            'Kode' => $kota->Kode,
            'Nama' => $kota->Nama,
            'NamaProvinsi' => $namaProvinsi,
            'ZonaWaktu' => $kota->ZonaWaktu->value,
            'ZonaWaktuIana' => $kota->ZonaWaktu->AmbilZonaIana(),
        ];
    }
}
