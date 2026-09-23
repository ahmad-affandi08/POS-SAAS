<?php

declare(strict_types=1);

namespace App\Domain\Pajak\Kueri;

use App\Domain\Pajak\Model\JenisPajak;
use App\Domain\Pajak\Model\KelompokPajak;
use App\Domain\Pajak\Model\KelompokPajakDetail;

/**
 * Kelompok pajak tenant aktif (F-01 langkah 3 & 4) dan data jenis pajak platform untuk domain lain.
 */
final class DaftarKelompokPajak
{
    /**
     * @return list<array{Nama: string, Pajak: list<array{KodeJenisPajak: string, NamaJenisPajak: string, DasarPengenaan: string, LabelDasarPengenaan: string}>}>
     */
    public function Ambil(): array
    {
        return array_values(KelompokPajak::query()->with('Detail.JenisPajak')->orderBy('Id')->get()->map(fn (KelompokPajak $kelompok): array => [
            'Nama' => $kelompok->Nama,
            'Pajak' => array_values($kelompok->Detail->map(fn (KelompokPajakDetail $detail): array => [
                'KodeJenisPajak' => $detail->JenisPajak->Kode,
                'NamaJenisPajak' => $detail->JenisPajak->Nama,
                'DasarPengenaan' => $detail->DasarPengenaan->value,
                'LabelDasarPengenaan' => $detail->DasarPengenaan->AmbilLabel(),
            ])->all()),
        ])->all());
    }

    /** Id kelompok pajak tenant dengan nama tertentu (tanpa beda huruf besar/kecil). */
    public function CariIdBerdasarkanNama(string $nama): ?int
    {
        $kelompok = KelompokPajak::query()->whereRaw('LOWER(Nama) = ?', [mb_strtolower(trim($nama))])->first();

        return $kelompok?->Id;
    }

    /**
     * Jenis pajak platform per kode (P-02).
     *
     * @param  list<string>  $kode
     * @return array<string, array{Kode: string, Nama: string, Cakupan: string}>
     */
    public function AmbilJenisPajak(array $kode): array
    {
        if ($kode === []) {
            return [];
        }

        $hasil = [];

        foreach (JenisPajak::query()->whereIn('Kode', array_values(array_unique($kode)))->get() as $jenis) {
            $hasil[$jenis->Kode] = ['Kode' => $jenis->Kode, 'Nama' => $jenis->Nama, 'Cakupan' => $jenis->Cakupan->value];
        }

        return $hasil;
    }
}
