<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Kueri;

use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pengelola\Referensi\Aksi\TinjauHariLiburTahun;
use App\Domain\Pengelola\Referensi\Model\PersetujuanDataMaster;
use App\Domain\Referensi\Model\HariLibur;

/**
 * Daftar hari libur satu tahun beserta status tinjauan putaran berjalan (P-02).
 */
final class DaftarHariLibur
{
    /**
     * @return array{HariLibur: list<array<string, mixed>>, IdPengajuMenunggu: list<int>, PeninjauMenunggu: list<int>}
     */
    public function AmbilTahun(int $tahun): array
    {
        $hari = HariLibur::query()->whereYear('Tanggal', $tahun)->orderBy('Tanggal')->get();
        $menunggu = $hari->filter(fn (HariLibur $item) => $item->Status === StatusDataMaster::MenungguTinjauan);

        $peninjau = $menunggu->isEmpty() ? [] : PersetujuanDataMaster::query()
            ->where('JenisData', TinjauHariLiburTahun::JENIS_DATA)
            ->where('IdData', $tahun)
            ->where('Putaran', (int) $menunggu->max('PutaranTinjauan'))
            ->pluck('IdPenggunaPengelola')
            ->all();

        return [
            'HariLibur' => array_values($hari->map(fn (HariLibur $item): array => [
                'Uuid' => $item->Uuid,
                'Tanggal' => $item->Tanggal->toDateString(),
                'Nama' => $item->Nama,
                'Jenis' => $item->Jenis->value,
                'Status' => $item->Status->value,
                'NomorDasarHukum' => $item->NomorDasarHukum,
                'PembatalanMenunggu' => $item->CekPembatalanMenunggu(),
                'AlasanPembatalan' => $item->AlasanPembatalan,
                'IdPengajuBatal' => $item->IdPenggunaPengelolaPengajuBatal,
                'DibatalkanPada' => $item->DibatalkanPada?->toIso8601String(),
            ])->all()),
            'IdPengajuMenunggu' => array_values(array_unique(array_filter(
                $menunggu->pluck('IdPenggunaPengelolaPengaju')->all(),
                fn ($id) => is_int($id),
            ))),
            'PeninjauMenunggu' => array_values(array_map(intval(...), $peninjau)),
        ];
    }
}
