<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Kueri;

use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pajak\Model\JenisPajak;
use App\Domain\Pajak\Model\TarifPajak;
use App\Domain\Pengelola\Referensi\Aksi\TinjauTarifPajak;
use App\Domain\Pengelola\Referensi\Enum\KeputusanTinjauan;
use App\Domain\Pengelola\Referensi\Model\PersetujuanDataMaster;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Daftar tarif pajak master untuk Platform Pengelola beserta status tinjauan putaran terakhir (P-02).
 */
final class DaftarTarifPajak
{
    private const PER_HALAMAN = 50;

    /**
     * @return LengthAwarePaginator<int, TarifPajak>
     */
    public function Cari(?StatusDataMaster $status): LengthAwarePaginator
    {
        return TarifPajak::query()
            ->with('JenisPajak')
            ->when($status !== null, fn ($kueri) => $kueri->where('Status', $status?->value))
            ->orderBy('IdJenisPajak')
            ->orderBy('KodeWilayah')
            ->orderByDesc('BerlakuMulai')
            ->paginate(self::PER_HALAMAN, ['*'], 'halaman')
            ->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    public function Petakan(TarifPajak $tarif): array
    {
        $putaran = $tarif->DiajukanPada === null ? collect() : PersetujuanDataMaster::query()
            ->with('Peninjau:Id,Nama')
            ->where('JenisData', TinjauTarifPajak::JENIS_DATA)
            ->where('IdData', $tarif->Id)
            ->where('DibuatPada', '>=', $tarif->DiajukanPada)
            ->orderBy('Id')
            ->get();

        $berakhir = $tarif->Status === StatusDataMaster::Terbit && $tarif->BerlakuSampai?->isPast() === true && ! $tarif->BerlakuSampai->isToday();

        return [
            'Uuid' => $tarif->Uuid,
            'KodeJenisPajak' => $tarif->JenisPajak->Kode,
            'NamaJenisPajak' => $tarif->JenisPajak->Nama,
            'Tarif' => $tarif->Tarif,
            'PengaliDppPembilang' => $tarif->PengaliDppPembilang,
            'PengaliDppPenyebut' => $tarif->PengaliDppPenyebut,
            'KodeWilayah' => $tarif->KodeWilayah,
            'BiayaLayananMasukDpp' => $tarif->BiayaLayananMasukDpp,
            'BerlakuMulai' => $tarif->BerlakuMulai->toDateString(),
            'BerlakuSampai' => $tarif->BerlakuSampai?->toDateString(),
            'Status' => $berakhir ? 'Berakhir' : $tarif->Status->value,
            'NomorDasarHukum' => $tarif->NomorDasarHukum,
            'TautanDasarHukum' => $tarif->TautanDasarHukum,
            'IdPengaju' => $tarif->IdPenggunaPengelolaPengaju,
            'PersetujuanDibutuhkan' => $tarif->CekNasional() ? TinjauTarifPajak::PENYETUJU_NASIONAL : TinjauTarifPajak::PENYETUJU_DAERAH,
            'Persetujuan' => $putaran->map(fn (PersetujuanDataMaster $keputusan): array => [
                'Peninjau' => $keputusan->Peninjau->Nama,
                'IdPeninjau' => $keputusan->IdPenggunaPengelola,
                'Keputusan' => $keputusan->Keputusan->value,
                'Catatan' => $keputusan->Catatan,
            ])->values()->all(),
            'JumlahSetuju' => $putaran->where('Keputusan', KeputusanTinjauan::Setuju)->count(),
        ];
    }

    /**
     * @return list<array{Kode: string, Nama: string, Cakupan: string}>
     */
    public function AmbilJenisPajak(): array
    {
        return array_values(JenisPajak::query()->orderBy('Id')->get()->map(fn (JenisPajak $jenis): array => [
            'Kode' => $jenis->Kode,
            'Nama' => $jenis->Nama,
            'Cakupan' => $jenis->Cakupan->value,
        ])->all());
    }
}
