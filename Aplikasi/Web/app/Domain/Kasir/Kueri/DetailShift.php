<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Kueri;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Kueri\JurnalSumber;
use App\Domain\Kasir\Model\KategoriKas;
use App\Domain\Kasir\Model\MutasiKas;
use App\Domain\Kasir\Model\Shift;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\DaftarPerangkat;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Penjualan\Kueri\DaftarPenjualan;

/**
 * Detail shift back-office (F-06): data pembukaan, pecahan kas awal, ringkasan kas non-penjualan, dan daftar mutasi
 * kas beserta kategori, pencatat, penyetuju, dan jurnalnya. F-07b: penjualan shift (lewat kueri publik Penjualan).
 * F-11: laporan shift X/Z (`LaporanShift`) dan data tutup shift (kas seharusnya/aktual/selisih, alasan, penyetuju,
 * pecahan, non-tunai per metode vs sistem, jurnal selisih). Shift tenant lain atau di outlet di luar akses = null.
 */
final class DetailShift
{
    public function __construct(
        private readonly PetaUuidOutlet $outlet,
        private readonly DaftarPerangkat $perangkat,
        private readonly AnggotaOutlet $anggota,
        private readonly JurnalSumber $jurnal,
        private readonly DaftarPenjualan $penjualan,
        private readonly LaporanShift $laporan,
    ) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array<string, mixed>|null
     */
    public function Ambil(string $uuid, ?array $idOutletBoleh): ?array
    {
        $shift = Shift::query()->where('Uuid', $uuid)->first();

        if ($shift === null || ($idOutletBoleh !== null && ! in_array($shift->IdOutlet, $idOutletBoleh, true))) {
            return null;
        }

        $mutasi = MutasiKas::query()->where('IdShift', $shift->Id)->orderBy('DicatatPada')->orderBy('Id')->get();
        $kategori = KategoriKas::query()->whereIn('Id', $mutasi->pluck('IdKategoriKas')->filter()->all())->get()->keyBy('Id');
        $nama = $this->anggota->AmbilNama(array_values(array_filter([
            $shift->DibukaOleh,
            $shift->DitutupOleh,
            $shift->IdPenyetujuSelisih,
            ...$mutasi->pluck('DicatatOleh')->all(),
            ...$mutasi->pluck('DisetujuiOleh')->filter()->all(),
        ])));
        $total = DaftarShift::AmbilTotalMutasi([$shift->Id])[$shift->Id] ?? [];

        return [
            'Shift' => [
                'Uuid' => $shift->Uuid,
                'NamaOutlet' => $this->outlet->AmbilRingkas([$shift->IdOutlet])[0]['Nama'] ?? '',
                'Perangkat' => $this->perangkat->AmbilLabel([$shift->IdPerangkat])[$shift->IdPerangkat] ?? '',
                'NamaKasir' => $nama[$shift->DibukaOleh]['Nama'] ?? '',
                'DibukaPada' => $shift->DibukaPada->toIso8601String(),
                'DiterimaPada' => $shift->DiterimaPada->toIso8601String(),
                'TanggalBisnis' => $shift->TanggalBisnis->toDateString(),
                'Status' => $shift->Status->value,
                'LabelStatus' => $shift->Status->AmbilLabel(),
                'Bersama' => $shift->Bersama,
                'PerluTinjauan' => $shift->PerluTinjauan,
                'AlasanTinjauan' => $shift->AlasanTinjauan,
                'KasAwal' => $shift->KasAwal,
                'PecahanKasAwal' => $shift->PecahanKasAwal ?? [],
            ] + DaftarShift::HitungRingkasan($shift->KasAwal, $total),
            'MutasiKas' => array_values($mutasi->map(function (MutasiKas $m) use ($kategori, $nama): array {
                $jurnal = $this->jurnal->Ambil(JenisSumberJurnal::MutasiKas, $m->Id)[0] ?? null;

                return [
                    'Uuid' => $m->Uuid,
                    'Jenis' => $m->Jenis->value,
                    'LabelJenis' => $m->Jenis->AmbilLabel(),
                    'NamaKategori' => $m->IdKategoriKas === null ? null : $kategori->get($m->IdKategoriKas)?->Nama,
                    'Jumlah' => $m->Jumlah,
                    'Catatan' => $m->Catatan,
                    'DicatatOleh' => $nama[$m->DicatatOleh]['Nama'] ?? '',
                    'DicatatPada' => $m->DicatatPada->toIso8601String(),
                    'DisetujuiOleh' => $m->DisetujuiOleh === null ? null : ($nama[$m->DisetujuiOleh]['Nama'] ?? ''),
                    'NomorJurnal' => $jurnal['Nomor'] ?? null,
                    'UuidJurnal' => $jurnal['Uuid'] ?? null,
                    'PerluTinjauan' => $m->PerluTinjauan,
                    'AlasanTinjauan' => $m->AlasanTinjauan,
                ];
            })->all()),
            'Penjualan' => $this->penjualan->AmbilUntukShift($shift->Id),
            // F-11: laporan X (shift berjalan) / Z (shift tertutup) dari data server saat ini.
            'Laporan' => $this->laporan->Hitung($shift)->KeLarik(),
            'Tutup' => $this->SusunTutup($shift, $nama),
        ];
    }

    /**
     * Data tutup shift (F-11); null bila shift belum ditutup.
     *
     * @param  array<int, array{Uuid: string, Nama: string}>  $nama
     * @return array<string, mixed>|null
     */
    private function SusunTutup(Shift $shift, array $nama): ?array
    {
        if ($shift->DitutupPada === null) {
            return null;
        }

        $jurnal = $this->jurnal->Ambil(JenisSumberJurnal::TutupShift, $shift->Id)[0] ?? null;

        return [
            'DitutupOleh' => $shift->DitutupOleh === null ? '' : ($nama[$shift->DitutupOleh]['Nama'] ?? ''),
            'DitutupPada' => $shift->DitutupPada->toIso8601String(),
            'KasSeharusnya' => $shift->KasSeharusnya,
            'KasAktual' => $shift->KasAktual,
            'Selisih' => $shift->Selisih,
            'AlasanSelisih' => $shift->AlasanSelisih,
            'Penyetuju' => $shift->IdPenyetujuSelisih === null ? null : ($nama[$shift->IdPenyetujuSelisih]['Nama'] ?? ''),
            'PecahanKasAkhir' => $shift->PecahanKasAkhir ?? [],
            'NonTunai' => $shift->RingkasanNonTunai ?? [],
            'NomorJurnal' => $jurnal['Nomor'] ?? null,
            'UuidJurnal' => $jurnal['Uuid'] ?? null,
        ];
    }

    /**
     * Uuid shift pemilik mutasi kas (tautan sumber jurnal); null bila tidak ada atau di luar akses.
     *
     * @param  list<int>|null  $idOutletBoleh
     */
    public function CariUuidShiftDariMutasi(string $uuidMutasi, ?array $idOutletBoleh): ?string
    {
        $mutasi = MutasiKas::query()->where('Uuid', $uuidMutasi)->first();
        $shift = $mutasi === null ? null : Shift::query()->whereKey($mutasi->IdShift)->first();

        if ($shift === null || ($idOutletBoleh !== null && ! in_array($shift->IdOutlet, $idOutletBoleh, true))) {
            return null;
        }

        return $shift->Uuid;
    }
}
