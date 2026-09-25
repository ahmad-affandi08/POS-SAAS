<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Laporan;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Laporan\Data\DataPeriodeLaporan;
use App\Domain\Laporan\Kueri\LaporanPajak;
use App\Domain\Laporan\Kueri\LaporanPenjualan;
use App\Domain\Laporan\Kueri\LaporanStok;
use App\Domain\Laporan\Layanan\PenulisCsvLaporan;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Penjualan\Kueri\AgregatPenjualan;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Respons\ResponsTabel;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan back-office F-14a ("Rincian F-14a"), baca saja, dibatasi outlet akses pelaku:
 * - penjualan (`laporan.penjualan.lihat`): tab ringkasan harian, per produk (TabelData mode server lewat URL yang sama
 *   dengan `Accept: application/json`), kategori, jam, kasir, kanal, metode bayar, diskon; ekspor CSV sesuai saring;
 * - pajak (`laporan.keuangan.lihat`): PB1/PBJT per outlet per bulan & PPN keluaran per bulan; ekspor CSV;
 * - stok (`persediaan.lihat`): nilai persediaan pada tanggal & stok kritis; ekspor CSV.
 */
final class LaporanKontroler extends DasarKelolaKontroler
{
    /** Batas periode laporan pajak (per bulan). */
    private const MAKS_HARI_PAJAK = 366;

    public function Penjualan(Request $permintaan, LaporanPenjualan $laporan, TanggalBisnisOutlet $tanggal): Response|JsonResponse
    {
        $saring = $laporan->BacaSaring($permintaan->query(), $this->IdOutletBoleh(), $tanggal->Hitung(null));
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), AgregatPenjualan::KOLOM_URUT_PRODUK, AgregatPenjualan::URUT_BAWAAN_PRODUK);

        if (ResponsTabel::MintaData($permintaan)) {
            return response()->json($laporan->AmbilIsiTab($saring['Tab'], $saring['Saring'], $tabel));
        }

        return Inertia::render('Kelola/Laporan/Penjualan', $laporan->AmbilHalaman($saring, $this->IdOutletBoleh(), $tabel));
    }

    public function EksporPenjualan(Request $permintaan, LaporanPenjualan $laporan, TanggalBisnisOutlet $tanggal): StreamedResponse
    {
        $saring = $laporan->BacaSaring($permintaan->query(), $this->IdOutletBoleh(), $tanggal->Hitung(null));
        $cari = is_string($permintaan->query('cari')) ? mb_substr(trim($permintaan->query('cari')), 0, 100) : '';
        [$judul, $baris] = $laporan->AmbilEkspor($saring['Tab'], $saring['Saring'], $cari);
        $periode = $saring['Periode'];

        return PenulisCsvLaporan::Alirkan("laporan-penjualan-{$saring['Tab']}-{$periode->dari->toDateString()}-{$periode->sampai->toDateString()}", $judul, $baris);
    }

    public function Pajak(Request $permintaan, LaporanPajak $laporan, PetaUuidOutlet $outlet, TanggalBisnisOutlet $tanggal): Response
    {
        [$periode, $uuidOutlet, $idOutlet] = $this->BacaSaringPajak($permintaan, $outlet, $tanggal->Hitung(null));

        return Inertia::render('Kelola/Laporan/Pajak', [
            'Saring' => ['Dari' => $periode->dari->toDateString(), 'Sampai' => $periode->sampai->toDateString(), 'Outlet' => $uuidOutlet],
            'Peringatan' => $periode->peringatan,
            'OpsiOutlet' => array_map(fn (array $o): array => ['Nilai' => $o['Uuid'], 'Label' => $o['Nama']], $outlet->AmbilRingkas($this->IdOutletBoleh())),
            ...$laporan->Ambil($periode->dari, $periode->sampai, $idOutlet),
        ]);
    }

    public function EksporPajak(Request $permintaan, LaporanPajak $laporan, PetaUuidOutlet $outlet, TanggalBisnisOutlet $tanggal): StreamedResponse
    {
        [$periode, , $idOutlet] = $this->BacaSaringPajak($permintaan, $outlet, $tanggal->Hitung(null));
        $isi = $laporan->Ambil($periode->dari, $periode->sampai, $idOutlet);
        $ppn = $permintaan->query('jenis') === 'ppn';
        $kolom = $ppn
            ? ['Bulan', 'NamaJenisPajak', 'Tarif', 'Dpp', 'Pajak', 'DppRetur', 'PajakRetur', 'DppBersih', 'PajakBersih', 'JumlahTransaksi']
            : ['Bulan', 'NamaOutlet', 'NamaJenisPajak', 'Tarif', 'Dpp', 'Pajak', 'DppRetur', 'PajakRetur', 'DppBersih', 'PajakBersih', 'JumlahTransaksi'];
        $judul = $ppn
            ? ['Bulan', 'Jenis pajak', 'Tarif (%)', 'DPP', 'Pajak', 'DPP retur', 'Pajak retur', 'DPP bersih', 'Pajak bersih', 'Jumlah transaksi']
            : ['Bulan', 'Outlet', 'Jenis pajak', 'Tarif (%)', 'DPP', 'Pajak', 'DPP retur', 'Pajak retur', 'DPP bersih', 'Pajak bersih', 'Jumlah transaksi'];
        $baris = array_map(fn (array $b): array => array_map(fn (string $k): string|int|null => self::Sel($b[$k] ?? null), $kolom), $ppn ? $isi['Ppn'] : $isi['Pbjt']);

        return PenulisCsvLaporan::Alirkan('laporan-pajak-'.($ppn ? 'ppn' : 'pbjt')."-{$periode->dari->toDateString()}-{$periode->sampai->toDateString()}", $judul, $baris);
    }

    public function Stok(Request $permintaan, LaporanStok $laporan, TanggalBisnisOutlet $tanggal): Response
    {
        [$tab, $pada, $uuidGudang] = $this->BacaSaringStok($permintaan, $tanggal->Hitung(null));
        $gudang = $laporan->AmbilGudang($this->IdOutletBoleh());

        return Inertia::render('Kelola/Laporan/Stok', [
            'Saring' => ['Tab' => $tab, 'Tanggal' => $pada->toDateString(), 'Gudang' => $uuidGudang],
            'OpsiGudang' => array_values(array_map(fn ($g): array => ['Nilai' => $g->uuid, 'Label' => $g->namaOutlet === null ? $g->nama : "{$g->nama} ({$g->namaOutlet})"], $gudang)),
            'Nilai' => $tab === 'nilai' ? $laporan->NilaiPersediaan($pada, $this->IdOutletBoleh(), $uuidGudang) : null,
            'Kritis' => $tab === 'kritis' ? $laporan->StokKritis($this->IdOutletBoleh(), $uuidGudang) : null,
        ]);
    }

    public function EksporStok(Request $permintaan, LaporanStok $laporan, TanggalBisnisOutlet $tanggal): StreamedResponse
    {
        [$tab, $pada, $uuidGudang] = $this->BacaSaringStok($permintaan, $tanggal->Hitung(null));

        if ($tab === 'kritis') {
            $isi = $laporan->StokKritis($this->IdOutletBoleh(), $uuidGudang)['Baris'];

            return PenulisCsvLaporan::Alirkan('laporan-stok-kritis', ['Produk', 'SKU', 'Satuan', 'Lokasi stok', 'Outlet', 'Saldo', 'Stok minimum', 'Kekurangan'], array_map(
                fn (array $b): array => [$b['NamaProduk'], $b['Sku'], $b['SimbolSatuan'], $b['NamaGudang'], $b['NamaOutlet'], $b['Saldo'], $b['StokMinimum'], $b['Kekurangan']],
                $isi,
            ));
        }

        $isi = $laporan->NilaiPersediaan($pada, $this->IdOutletBoleh(), $uuidGudang);
        $baris = [
            ...array_map(fn (array $b): array => ['Lokasi stok', "{$b['NamaGudang']} ({$b['NamaOutlet']})", $b['JumlahProduk'], $b['Nilai']], $isi['PerGudang']),
            ...array_map(fn (array $b): array => ['Kategori', $b['NamaKategori'], $b['JumlahProduk'], $b['Nilai']], $isi['PerKategori']),
        ];

        return PenulisCsvLaporan::Alirkan("laporan-nilai-persediaan-{$pada->toDateString()}", ['Kelompok', 'Nama', 'Jumlah produk', 'Nilai persediaan'], $baris);
    }

    /**
     * @return array{0: DataPeriodeLaporan, 1: string, 2: list<int>|null}
     */
    private function BacaSaringPajak(Request $permintaan, PetaUuidOutlet $outlet, CarbonImmutable $hariIni): array
    {
        $sampai = DataPeriodeLaporan::Urai($permintaan->query('sampai')) ?? $hariIni;
        $dari = $permintaan->query('dari') ?? $sampai->startOfMonth()->toDateString();
        $periode = DataPeriodeLaporan::Baca($dari, $sampai->toDateString(), $hariIni, 31, self::MAKS_HARI_PAJAK);
        $uuidOutlet = is_string($permintaan->query('outlet')) ? $permintaan->query('outlet') : '';
        $boleh = $this->IdOutletBoleh();
        $idOutlet = $boleh;

        if ($uuidOutlet !== '') {
            $id = $outlet->AmbilIdDariUuid([$uuidOutlet])[$uuidOutlet] ?? null;
            $idOutlet = $id !== null && ($boleh === null || in_array($id, $boleh, true)) ? [$id] : [];
        }

        return [$periode, $uuidOutlet, $idOutlet];
    }

    /**
     * @return array{0: string, 1: CarbonImmutable, 2: string}
     */
    private function BacaSaringStok(Request $permintaan, CarbonImmutable $hariIni): array
    {
        $tab = $permintaan->query('tab') === 'kritis' ? 'kritis' : 'nilai';
        $pada = DataPeriodeLaporan::Urai($permintaan->query('tanggal')) ?? $hariIni;
        $uuidGudang = is_string($permintaan->query('gudang')) ? $permintaan->query('gudang') : '';

        return [$tab, $pada->greaterThan($hariIni) ? $hariIni : $pada, $uuidGudang];
    }

    private static function Sel(mixed $nilai): string|int|null
    {
        return is_string($nilai) || is_int($nilai) || $nilai === null ? $nilai : null;
    }
}
