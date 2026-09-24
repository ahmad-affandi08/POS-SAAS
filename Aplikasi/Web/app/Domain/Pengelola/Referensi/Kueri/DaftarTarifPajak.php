<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Kueri;

use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Pajak\Model\JenisPajak;
use App\Domain\Pajak\Model\TarifPajak;
use App\Domain\Pengelola\Referensi\Aksi\TinjauTarifPajak;
use App\Domain\Pengelola\Referensi\Enum\KeputusanTinjauan;
use App\Domain\Pengelola\Referensi\Model\PersetujuanDataMaster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Daftar tarif pajak master untuk Platform Pengelola beserta status tinjauan putaran terakhir (P-02).
 */
final class DaftarTarifPajak
{
    public const KOLOM_URUT = ['Pajak', 'Tarif', 'BerlakuMulai'];

    public const KOLOM_SARING = ['Status', 'KodeJenisPajak'];

    /** Urutan bawaan: per jenis pajak & wilayah, tanggal berlaku terbaru di atas. */
    public const URUT_BAWAAN = 'Pajak,-BerlakuMulai';

    /** Status tampilan tarif terbit yang `BerlakuSampai`-nya sudah lewat (bukan status di basis data). */
    public const STATUS_BERAKHIR = 'Berakhir';

    /**
     * Tarif untuk `TabelData` (D-16): cari jenis pajak, kode wilayah, atau nomor dasar hukum; saring status
     * (pilihan banyak, termasuk `Berakhir`) dan jenis pajak.
     *
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan): array
    {
        $pola = PenerapKueriTabel::PolaCari($permintaan->cari);
        $hariIni = now('Asia/Jakarta')->toDateString();
        $status = $permintaan->AmbilDaftar('Status', [StatusDataMaster::Draf->value, StatusDataMaster::MenungguTinjauan->value, StatusDataMaster::Terbit->value, self::STATUS_BERAKHIR]);
        $jenis = $permintaan->AmbilDaftar('KodeJenisPajak');

        $kueri = TarifPajak::query()
            ->with('JenisPajak')
            ->when($permintaan->cari !== '', fn (Builder $kueri) => $kueri->where(fn (Builder $dalam) => $dalam
                ->where('NomorDasarHukum', 'like', $pola)
                ->orWhere('KodeWilayah', 'like', $pola)
                ->orWhereIn('IdJenisPajak', JenisPajak::query()->select('Id')->where('Nama', 'like', $pola)->orWhere('Kode', 'like', $pola))))
            ->when($jenis !== [], fn (Builder $kueri) => $kueri->whereIn('IdJenisPajak', JenisPajak::query()->select('Id')->whereIn('Kode', $jenis)))
            ->when($status !== [], fn (Builder $kueri) => $kueri->where(function (Builder $dalam) use ($status, $hariIni): void {
                foreach ($status as $nilai) {
                    match ($nilai) {
                        self::STATUS_BERAKHIR => $dalam->orWhere(fn (Builder $k) => $k->where('Status', StatusDataMaster::Terbit->value)->where('BerlakuSampai', '<', $hariIni)),
                        StatusDataMaster::Terbit->value => $dalam->orWhere(fn (Builder $k) => $k->where('Status', $nilai)->where(fn (Builder $b) => $b->whereNull('BerlakuSampai')->orWhere('BerlakuSampai', '>=', $hariIni))),
                        default => $dalam->orWhere('Status', $nilai),
                    };
                }
            }));

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, [
            'Pajak' => function (Builder $kueri, bool $turun): void {
                $kueri->orderBy('IdJenisPajak', $turun ? 'desc' : 'asc')->orderBy('KodeWilayah', $turun ? 'desc' : 'asc');
            },
            'Tarif' => 'Tarif',
            'BerlakuMulai' => 'BerlakuMulai',
        ], fn (Collection $tarif): array => $this->PetakanTarif(array_values($tarif->all())));
    }

    /**
     * Memetakan satu halaman tarif; keputusan peninjau putaran berjalan dimuat dalam satu kueri (tanpa N+1).
     *
     * @param  list<TarifPajak>  $daftar
     * @return list<array<string, mixed>>
     */
    private function PetakanTarif(array $daftar): array
    {
        $keputusan = PersetujuanDataMaster::query()
            ->with('Peninjau:Id,Nama')
            ->where('JenisData', TinjauTarifPajak::JENIS_DATA)
            ->whereIn('IdData', array_map(fn (TarifPajak $tarif) => $tarif->Id, $daftar))
            ->orderBy('Id')
            ->get()
            ->groupBy(fn (PersetujuanDataMaster $item) => $item->IdData.':'.$item->Putaran);

        return array_map(function (TarifPajak $tarif) use ($keputusan): array {
            $putaran = $tarif->Status === StatusDataMaster::Draf ? collect() : $keputusan->get($tarif->Id.':'.$tarif->PutaranTinjauan, collect());
            $berakhir = $tarif->Status === StatusDataMaster::Terbit && $tarif->BerlakuSampai !== null && $tarif->BerlakuSampai->toDateString() < now('Asia/Jakarta')->toDateString();

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
                'Status' => $berakhir ? self::STATUS_BERAKHIR : $tarif->Status->value,
                'NomorDasarHukum' => $tarif->NomorDasarHukum,
                'TautanDasarHukum' => $tarif->TautanDasarHukum,
                'IdPengaju' => $tarif->IdPenggunaPengelolaPengaju,
                'DaftarIdPenyusun' => $tarif->DaftarIdPenyusun ?? [],
                'PersetujuanDibutuhkan' => $tarif->CekNasional() ? TinjauTarifPajak::PENYETUJU_NASIONAL : TinjauTarifPajak::PENYETUJU_DAERAH,
                'Persetujuan' => $putaran->map(fn (PersetujuanDataMaster $item): array => [
                    'Peninjau' => $item->Peninjau->Nama,
                    'IdPeninjau' => $item->IdPenggunaPengelola,
                    'Keputusan' => $item->Keputusan->value,
                    'Catatan' => $item->Catatan,
                ])->values()->all(),
                'JumlahSetuju' => $putaran->where('Keputusan', KeputusanTinjauan::Setuju)->count(),
            ];
        }, $daftar);
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
