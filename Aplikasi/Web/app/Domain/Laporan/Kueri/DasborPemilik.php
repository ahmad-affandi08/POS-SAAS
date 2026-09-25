<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Kueri;

use App\Domain\Kasir\Kueri\RingkasanShiftDasbor;
use App\Domain\Laporan\Model\RingkasanPenjualanHarian;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Penjualan\Data\DataAgregatPenjualan;
use App\Domain\Penjualan\Data\DataSaringLaporanPenjualan;
use App\Domain\Penjualan\Kueri\AgregatPenjualan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Dasbor pemilik di beranda `/kelola` (F-14a, izin `laporan.penjualan.lihat`, dibatasi outlet akses). Hari berjalan
 * dihitung langsung dari dokumen; hari-hari sebelumnya dari `RingkasanPenjualanHarian` (ringan, PRD §17 "data dasbor").
 * Isi: omzet bersih, transaksi, rata-rata keranjang, dan laba kotor hari ini vs kemarin vs hari yang sama minggu lalu;
 * grafik 14 hari; 5 produk terlaris 7 hari; penjualan per outlet hari ini; stok kritis (bila berizin `persediaan.lihat`);
 * shift terbuka & selisih kas terbaru; jumlah penjualan `PerluTinjauan`.
 */
final class DasborPemilik
{
    private const HARI_GRAFIK = 14;

    public function __construct(
        private readonly AgregatPenjualan $agregat,
        private readonly PetaUuidOutlet $outlet,
        private readonly AnggotaOutlet $anggota,
        private readonly RingkasanShiftDasbor $shift,
        private readonly LaporanStok $laporanStok,
    ) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array<string, mixed>
     */
    public function Ambil(CarbonImmutable $hariIni, ?array $idOutletBoleh, bool $bolehStok): array
    {
        $hariIni = $hariIni->startOfDay();
        $saringHariIni = new DataSaringLaporanPenjualan($hariIni, $hariIni, $idOutletBoleh);
        $awalGrafik = $hariIni->subDays(self::HARI_GRAFIK - 1);
        $perTanggal = $this->AmbilRingkasan($awalGrafik, $hariIni->subDay(), $idOutletBoleh);
        $perTanggal[$hariIni->toDateString()] = $this->agregat->Total($saringHariIni);
        $grafik = [];

        for ($t = $awalGrafik; $t->lessThanOrEqualTo($hariIni); $t = $t->addDay()) {
            $a = $perTanggal[$t->toDateString()] ?? DataAgregatPenjualan::Nol();
            $grafik[] = ['Tanggal' => $t->toDateString(), 'Bersih' => $a->Bersih()->KeString(), 'LabaKotor' => $a->LabaKotor()->KeString(), 'JumlahTransaksi' => $a->jumlahTransaksi];
        }

        $mingguLalu = $hariIni->subDays(7);
        $perMingguLalu = $this->AmbilRingkasan($mingguLalu, $mingguLalu, $idOutletBoleh);
        $outlet = $this->outlet->AmbilRingkas($idOutletBoleh);
        $perOutlet = $this->agregat->Agregasi($saringHariIni, ['Outlet']);
        $shift = $this->shift->Ambil($idOutletBoleh);
        $namaOutlet = array_column($outlet, 'Nama', 'Id');
        $namaPengguna = $this->anggota->AmbilNama(array_values(array_filter([
            ...array_column($shift['Terbuka'], 'DibukaOleh'),
            ...array_column($shift['Tertutup'], 'DitutupOleh'),
        ])));

        return [
            'Tanggal' => $hariIni->toDateString(),
            'HariIni' => $perTanggal[$hariIni->toDateString()]->KeLarik(),
            'Kemarin' => ($perTanggal[$hariIni->subDay()->toDateString()] ?? DataAgregatPenjualan::Nol())->KeLarik(),
            'MingguLalu' => ($perMingguLalu[$mingguLalu->toDateString()] ?? DataAgregatPenjualan::Nol())->KeLarik(),
            'Grafik' => $grafik,
            'ProdukTerlaris' => array_map(fn (array $p): array => [
                'Kunci' => (string) $p['IdProduk'],
                'NamaProduk' => $p['NamaProduk'],
                'Qty' => $p['Qty'],
                'Bersih' => $p['Bersih'],
            ], $this->agregat->PerProduk(new DataSaringLaporanPenjualan($hariIni->subDays(6), $hariIni, $idOutletBoleh), batas: 5)),
            'PerOutlet' => array_map(fn (array $o): array => [
                'Kunci' => $o['Uuid'],
                'NamaOutlet' => $o['Nama'],
                'Bersih' => ($perOutlet[(string) $o['Id']]['Agregat'] ?? DataAgregatPenjualan::Nol())->Bersih()->KeString(),
                'JumlahTransaksi' => ($perOutlet[(string) $o['Id']]['Agregat'] ?? DataAgregatPenjualan::Nol())->jumlahTransaksi,
            ], $outlet),
            'StokKritis' => $bolehStok ? $this->laporanStok->StokKritis($idOutletBoleh, batas: 5) : null,
            'Shift' => [
                'JumlahTerbuka' => $shift['JumlahTerbuka'],
                'Terbuka' => array_map(fn (array $s): array => [
                    'Uuid' => $s['Uuid'],
                    'NamaOutlet' => $namaOutlet[$s['IdOutlet']] ?? '',
                    'NamaKasir' => $namaPengguna[$s['DibukaOleh']]['Nama'] ?? '',
                    'DibukaPada' => $s['DibukaPada'],
                ], $shift['Terbuka']),
                'Tertutup' => array_map(fn (array $s): array => [
                    'Uuid' => $s['Uuid'],
                    'NamaOutlet' => $namaOutlet[$s['IdOutlet']] ?? '',
                    'NamaKasir' => $s['DitutupOleh'] === null ? '' : ($namaPengguna[$s['DitutupOleh']]['Nama'] ?? ''),
                    'DitutupPada' => $s['DitutupPada'],
                    'Selisih' => $s['Selisih'],
                ], $shift['Tertutup']),
            ],
            'JumlahPerluTinjauan' => $this->agregat->HitungPerluTinjauan($idOutletBoleh),
        ];
    }

    /**
     * Agregat per tanggal dari tabel ringkasan (semua outlet yang boleh digabung).
     *
     * @param  list<int>|null  $idOutlet
     * @return array<string, DataAgregatPenjualan>
     */
    private function AmbilRingkasan(CarbonImmutable $dari, CarbonImmutable $sampai, ?array $idOutlet): array
    {
        $hasil = [];

        if ($idOutlet === [] || $dari->greaterThan($sampai)) {
            return $hasil;
        }

        $baris = RingkasanPenjualanHarian::query()
            ->whereBetween('TanggalBisnis', [$dari->toDateString(), $sampai->toDateString()])
            ->when($idOutlet !== null, fn (Builder $k) => $k->whereIn('IdOutlet', $idOutlet ?? []))
            ->get();

        foreach ($baris as $b) {
            $tanggal = $b->TanggalBisnis->toDateString();
            $agregat = LaporanPenjualan::DariRingkasan($b);
            $hasil[$tanggal] = isset($hasil[$tanggal]) ? $hasil[$tanggal]->Tambah($agregat) : $agregat;
        }

        return $hasil;
    }
}
