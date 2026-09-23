<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Katalog\Kueri;

use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pengelola\Katalog\Aksi\TinjauHargaPaket;
use App\Domain\Pengelola\Referensi\Model\PersetujuanDataMaster;
use App\Domain\Tenant\Model\HargaPaket;
use App\Domain\Tenant\Model\Paket;

/**
 * Riwayat versi harga satu paket beserta keputusan peninjau putaran berjalan (P-04).
 */
final class DaftarHargaPaket
{
    /**
     * @return list<array<string, mixed>>
     */
    public function AmbilUntukPaket(Paket $paket): array
    {
        $daftar = HargaPaket::query()->where('IdPaket', $paket->Id)->orderByDesc('BerlakuMulai')->orderByDesc('Id')->get();
        $keputusan = PersetujuanDataMaster::query()
            ->with('Peninjau:Id,Nama')
            ->where('JenisData', TinjauHargaPaket::JENIS_DATA)
            ->whereIn('IdData', $daftar->pluck('Id')->all())
            ->orderBy('Id')
            ->get()
            ->groupBy(fn (PersetujuanDataMaster $item) => $item->IdData.':'.$item->Putaran);

        return array_values($daftar->map(function (HargaPaket $harga) use ($keputusan): array {
            $putaran = $harga->Status === StatusDataMaster::Draf ? collect() : $keputusan->get($harga->Id.':'.$harga->PutaranTinjauan, collect());
            $berakhir = $harga->Status === StatusDataMaster::Terbit && $harga->BerlakuSampai !== null && $harga->BerlakuSampai->toDateString() < now('Asia/Jakarta')->toDateString();

            return [
                'Uuid' => $harga->Uuid,
                'HargaBulanan' => $harga->HargaBulanan,
                'HargaTahunan' => $harga->HargaTahunan,
                'BerlakuMulai' => $harga->BerlakuMulai->toDateString(),
                'BerlakuSampai' => $harga->BerlakuSampai?->toDateString(),
                'TerapkanKePelangganLama' => $harga->TerapkanKePelangganLama,
                'Status' => $berakhir ? 'Berakhir' : $harga->Status->value,
                'DaftarIdPenyusun' => $harga->DaftarIdPenyusun ?? [],
                'IdPengaju' => $harga->IdPenggunaPengelolaPengaju,
                'Persetujuan' => $putaran->map(fn (PersetujuanDataMaster $item): array => [
                    'Peninjau' => $item->Peninjau->Nama,
                    'IdPeninjau' => $item->IdPenggunaPengelola,
                    'Keputusan' => $item->Keputusan->value,
                    'Catatan' => $item->Catatan,
                ])->values()->all(),
            ];
        })->all());
    }
}
