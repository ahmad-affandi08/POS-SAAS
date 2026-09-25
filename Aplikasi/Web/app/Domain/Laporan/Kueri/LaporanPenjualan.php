<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Kueri;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Katalog\Kueri\ProdukUntukLaporan;
use App\Domain\Laporan\Data\DataPeriodeLaporan;
use App\Domain\Laporan\Model\RingkasanPenjualanHarian;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Organisasi\Kueri\ZonaWaktuOutlet;
use App\Domain\Penjualan\Data\DataAgregatPenjualan;
use App\Domain\Penjualan\Data\DataSaringLaporanPenjualan;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use App\Domain\Penjualan\Kueri\AgregatPenjualan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Laporan penjualan back-office F-14a (`/kelola/laporan/penjualan`, izin `laporan.penjualan.lihat`, dibatasi outlet
 * akses). Saring halaman: `tab`, `dari`/`sampai` (maks. 92 hari), `outlet` (Uuid), `kasir` (Uuid), `kanal`. Tab:
 * ringkasan harian (dari `RingkasanPenjualanHarian`; bila menyaring kasir/kanal dihitung langsung dari dokumen),
 * per produk (`TabelData` mode server), per kategori, per jam (heatmap hari × jam lokal outlet), per kasir, per kanal,
 * per metode bayar, dan diskon per kasir. Angka dari kueri publik domain Penjualan (`AgregatPenjualan`).
 */
final class LaporanPenjualan
{
    public const TAB = ['harian', 'produk', 'kategori', 'jam', 'kasir', 'kanal', 'metode', 'diskon'];

    public function __construct(
        private readonly AgregatPenjualan $agregat,
        private readonly PetaUuidOutlet $outlet,
        private readonly AnggotaOutlet $anggota,
        private readonly ProdukUntukLaporan $produk,
        private readonly ZonaWaktuOutlet $zonaWaktu,
    ) {}

    /**
     * Membaca saring halaman. Outlet di luar akses atau Uuid tidak dikenal = tanpa outlet (laporan kosong).
     *
     * @param  array<mixed>  $query
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Tab: string, Periode: DataPeriodeLaporan, UuidOutlet: string, UuidKasir: string, Kanal: string, Saring: DataSaringLaporanPenjualan}
     */
    public function BacaSaring(array $query, ?array $idOutletBoleh, CarbonImmutable $hariIni): array
    {
        $tab = is_string($query['tab'] ?? null) && in_array($query['tab'], self::TAB, true) ? $query['tab'] : 'harian';
        $periode = DataPeriodeLaporan::Baca($query['dari'] ?? null, $query['sampai'] ?? null, $hariIni);
        $uuidOutlet = is_string($query['outlet'] ?? null) ? $query['outlet'] : '';
        $idOutlet = $idOutletBoleh;

        if ($uuidOutlet !== '') {
            $id = $this->outlet->AmbilIdDariUuid([$uuidOutlet])[$uuidOutlet] ?? null;
            $idOutlet = $id !== null && ($idOutletBoleh === null || in_array($id, $idOutletBoleh, true)) ? [$id] : [];
        }

        $uuidKasir = is_string($query['kasir'] ?? null) ? $query['kasir'] : '';
        $idKasir = null;

        if ($uuidKasir !== '') {
            $idKasir = 0;

            foreach ($this->AmbilOpsiKasir($idOutletBoleh) as $opsi) {
                if ($opsi['Nilai'] === $uuidKasir) {
                    $idKasir = $opsi['Id'];
                }
            }
        }

        $kanal = KanalPenjualan::tryFrom(is_string($query['kanal'] ?? null) ? $query['kanal'] : '');

        return [
            'Tab' => $tab,
            'Periode' => $periode,
            'UuidOutlet' => $uuidOutlet,
            'UuidKasir' => $uuidKasir,
            'Kanal' => $kanal->value ?? '',
            'Saring' => new DataSaringLaporanPenjualan($periode->dari, $periode->sampai, $idOutlet, $idKasir, $kanal),
        ];
    }

    /**
     * Props halaman Inertia `Kelola/Laporan/Penjualan`.
     *
     * @param  array{Tab: string, Periode: DataPeriodeLaporan, UuidOutlet: string, UuidKasir: string, Kanal: string, Saring: DataSaringLaporanPenjualan}  $saring
     * @param  list<int>|null  $idOutletBoleh
     * @return array<string, mixed>
     */
    public function AmbilHalaman(array $saring, ?array $idOutletBoleh, DataPermintaanTabel $tabel): array
    {
        $s = $saring['Saring'];

        return [
            'Saring' => [
                'Tab' => $saring['Tab'],
                'Dari' => $saring['Periode']->dari->toDateString(),
                'Sampai' => $saring['Periode']->sampai->toDateString(),
                'Outlet' => $saring['UuidOutlet'],
                'Kasir' => $saring['UuidKasir'],
                'Kanal' => $saring['Kanal'],
            ],
            'Peringatan' => $saring['Periode']->peringatan,
            'MaksHari' => DataPeriodeLaporan::MAKS_HARI,
            'OpsiOutlet' => array_map(fn (array $o): array => ['Nilai' => $o['Uuid'], 'Label' => $o['Nama']], $this->outlet->AmbilRingkas($idOutletBoleh)),
            'OpsiKasir' => array_map(fn (array $o): array => ['Nilai' => $o['Nilai'], 'Label' => $o['Label']], $this->AmbilOpsiKasir($idOutletBoleh)),
            'OpsiKanal' => array_map(fn (KanalPenjualan $k): array => ['Nilai' => $k->value, 'Label' => $k->AmbilLabel()], KanalPenjualan::cases()),
            'Total' => $this->agregat->Total($s)->KeLarik(),
            'Isi' => $this->AmbilIsiTab($saring['Tab'], $s, $tabel),
        ];
    }

    /**
     * Data satu tab. `produk` = `{Data, Meta}` (TabelData mode server); tab lain = daftar baris (mode lokal).
     *
     * @return array<string, mixed>|list<array<string, mixed>>
     */
    public function AmbilIsiTab(string $tab, DataSaringLaporanPenjualan $saring, DataPermintaanTabel $tabel): array
    {
        return match ($tab) {
            'produk' => $this->agregat->PerProdukTabel($saring, $tabel),
            'kategori' => $this->PerKategori($saring),
            'jam' => $this->PerJam($saring),
            'kasir' => $this->PerKasir($saring),
            'kanal' => $this->PerKanal($saring),
            'metode' => $this->PerMetode($saring),
            'diskon' => $this->Diskon($saring),
            default => $this->Harian($saring),
        };
    }

    /**
     * Ringkasan harian: dari tabel ringkasan (cepat) bila tanpa saring kasir/kanal, selain itu dari dokumen.
     *
     * @return list<array<string, mixed>>
     */
    public function Harian(DataSaringLaporanPenjualan $saring): array
    {
        if ($saring->CekTanpaOutlet()) {
            return [];
        }

        $perTanggal = [];

        if ($saring->idKasir === null && $saring->kanal === null) {
            $baris = RingkasanPenjualanHarian::query()
                ->whereBetween('TanggalBisnis', [$saring->dari->toDateString(), $saring->sampai->toDateString()])
                ->when($saring->idOutlet !== null, fn (Builder $k) => $k->whereIn('IdOutlet', $saring->idOutlet ?? []))
                ->orderBy('TanggalBisnis')
                ->get();

            foreach ($baris as $b) {
                $tanggal = $b->TanggalBisnis->toDateString();
                $agregat = self::DariRingkasan($b);
                $perTanggal[$tanggal] = isset($perTanggal[$tanggal]) ? $perTanggal[$tanggal]->Tambah($agregat) : $agregat;
            }
        } else {
            foreach ($this->agregat->Agregasi($saring, ['Tanggal']) as $b) {
                $perTanggal[$b['Kunci'][0]] = $b['Agregat'];
            }
        }

        ksort($perTanggal);

        return array_values(array_map(fn (string $tanggal, DataAgregatPenjualan $a): array => ['Tanggal' => $tanggal, ...$a->KeLarik()], array_keys($perTanggal), $perTanggal));
    }

    /** Agregat satu baris ringkasan harian. */
    public static function DariRingkasan(RingkasanPenjualanHarian $b): DataAgregatPenjualan
    {
        return new DataAgregatPenjualan(
            Uang::Dari($b->Kotor),
            Uang::Dari($b->Diskon),
            Uang::Dari($b->Retur),
            Uang::Dari($b->Pajak),
            Uang::Dari($b->BiayaLayanan),
            Uang::Dari($b->Hpp),
            $b->JumlahTransaksi,
            $b->JumlahRetur,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function PerKategori(DataSaringLaporanPenjualan $saring): array
    {
        $produk = $this->agregat->PerProduk($saring);
        $kategori = $this->produk->AmbilKategori(array_column($produk, 'IdProduk'));
        $grup = [];

        foreach ($produk as $p) {
            $k = $kategori[$p['IdProduk']] ?? ['IdKategori' => null, 'NamaKategori' => ''];
            $kunci = (string) ($k['IdKategori'] ?? 0);
            $ada = $grup[$kunci] ?? [
                'Kunci' => $kunci,
                'NamaKategori' => $k['IdKategori'] === null ? 'Tanpa kategori' : $k['NamaKategori'],
                'JumlahProduk' => 0,
                'Qty' => Kuantitas::Nol(),
                'Kotor' => Uang::Nol(),
                'Diskon' => Uang::Nol(),
                'Retur' => Uang::Nol(),
                'Bersih' => Uang::Nol(),
                'Pajak' => Uang::Nol(),
                'Hpp' => Uang::Nol(),
                'LabaKotor' => Uang::Nol(),
            ];
            $ada['JumlahProduk']++;
            $ada['Qty'] = $ada['Qty']->Tambah(Kuantitas::Dari($p['Qty']));

            foreach (['Kotor', 'Diskon', 'Retur', 'Bersih', 'Pajak', 'Hpp', 'LabaKotor'] as $kolom) {
                $ada[$kolom] = $ada[$kolom]->Tambah(Uang::Dari($p[$kolom]));
            }

            $grup[$kunci] = $ada;
        }

        $hasil = array_values(array_map(fn (array $g): array => [
            'Kunci' => $g['Kunci'],
            'NamaKategori' => $g['NamaKategori'],
            'JumlahProduk' => $g['JumlahProduk'],
            'Qty' => $g['Qty']->KeString(),
            'Kotor' => $g['Kotor']->KeString(),
            'Diskon' => $g['Diskon']->KeString(),
            'Retur' => $g['Retur']->KeString(),
            'Bersih' => $g['Bersih']->KeString(),
            'Pajak' => $g['Pajak']->KeString(),
            'Hpp' => $g['Hpp']->KeString(),
            'LabaKotor' => $g['LabaKotor']->KeString(),
        ], $grup));
        usort($hasil, fn (array $a, array $b): int => Uang::Dari($b['Bersih'])->Bandingkan(Uang::Dari($a['Bersih'])) ?: strcmp($a['NamaKategori'], $b['NamaKategori']));

        return $hasil;
    }

    /**
     * Heatmap hari × jam lokal outlet (`Sel`) dan total per jam (`PerJam`, 24 baris untuk tabel yang bisa dibaca
     * pembaca layar).
     *
     * @return array{Sel: list<array{Hari: int, Jam: int, Bersih: string, JumlahTransaksi: int}>, PerJam: list<array{Jam: int, Bersih: string, JumlahTransaksi: int}>}
     */
    public function PerJam(DataSaringLaporanPenjualan $saring): array
    {
        $sel = $this->agregat->PerJam($saring, $this->zonaWaktu->AmbilSelisihDetik($saring->idOutlet));
        $perJam = [];

        foreach ($sel as $s) {
            $ada = $perJam[$s['Jam']] ?? ['Jam' => $s['Jam'], 'Bersih' => Uang::Nol(), 'JumlahTransaksi' => 0];
            $ada['Bersih'] = $ada['Bersih']->Tambah(Uang::Dari($s['Bersih']));
            $ada['JumlahTransaksi'] += $s['JumlahTransaksi'];
            $perJam[$s['Jam']] = $ada;
        }

        ksort($perJam);

        return [
            'Sel' => $sel,
            'PerJam' => array_values(array_map(fn (array $j): array => [...$j, 'Bersih' => $j['Bersih']->KeString()], $perJam)),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function PerKasir(DataSaringLaporanPenjualan $saring): array
    {
        $baris = $this->agregat->Agregasi($saring, ['Kasir']);
        $nama = $this->anggota->AmbilNama(array_map(fn (array $b): int => (int) $b['Kunci'][0], array_values($baris)));
        $hasil = [];

        foreach ($baris as $b) {
            $id = (int) $b['Kunci'][0];
            $hasil[] = ['Kunci' => $nama[$id]['Uuid'] ?? (string) $id, 'NamaKasir' => $nama[$id]['Nama'] ?? 'Pengguna tidak dikenal', ...$b['Agregat']->KeLarik()];
        }

        return self::UrutBersih($hasil);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function PerKanal(DataSaringLaporanPenjualan $saring): array
    {
        $hasil = [];

        foreach ($this->agregat->Agregasi($saring, ['Kanal']) as $b) {
            $kanal = KanalPenjualan::tryFrom($b['Kunci'][0]);
            $hasil[] = ['Kunci' => $b['Kunci'][0], 'LabelKanal' => $kanal?->AmbilLabel() ?? $b['Kunci'][0], ...$b['Agregat']->KeLarik()];
        }

        return self::UrutBersih($hasil);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function PerMetode(DataSaringLaporanPenjualan $saring): array
    {
        return array_values(array_map(fn (array $m): array => [
            'Kunci' => (string) $m['IdMetodePembayaran'],
            'NamaMetode' => $m['Nama'],
            'LabelJenis' => JenisMetodePembayaran::tryFrom($m['Jenis'])?->AmbilLabel() ?? $m['Jenis'],
            'Diterima' => $m['Diterima'],
            'Refund' => $m['Refund'],
            'Bersih' => $m['Bersih'],
            'JumlahTransaksi' => $m['JumlahTransaksi'],
        ], $this->agregat->PerMetode($saring)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function Diskon(DataSaringLaporanPenjualan $saring): array
    {
        $baris = $this->agregat->DiskonPerKasir($saring);
        $nama = $this->anggota->AmbilNama(array_column($baris, 'IdKasir'));

        return array_values(array_map(fn (array $b): array => [
            'Kunci' => $nama[$b['IdKasir']]['Uuid'] ?? (string) $b['IdKasir'],
            'NamaKasir' => $nama[$b['IdKasir']]['Nama'] ?? 'Pengguna tidak dikenal',
            'JumlahTransaksi' => $b['JumlahTransaksi'],
            'JumlahBerdiskon' => $b['JumlahBerdiskon'],
            'JumlahDisetujui' => $b['JumlahDisetujui'],
            'DiskonBaris' => $b['DiskonBaris'],
            'DiskonPesanan' => $b['DiskonPesanan'],
            'TotalDiskon' => $b['TotalDiskon'],
            'Kotor' => $b['Kotor'],
        ], $baris));
    }

    /**
     * Isi ekspor CSV tab aktif (semua baris sesuai saring; tab produk mengikuti kata cari).
     *
     * @return array{0: list<string>, 1: list<list<string|int|null>>}
     */
    public function AmbilEkspor(string $tab, DataSaringLaporanPenjualan $saring, string $cari = ''): array
    {
        $angka = ['Kotor', 'Diskon', 'Retur', 'Bersih', 'Pajak', 'BiayaLayanan', 'Hpp', 'LabaKotor', 'JumlahTransaksi', 'JumlahRetur'];
        $judulAngka = ['Kotor', 'Diskon', 'Retur', 'Bersih', 'Pajak', 'Biaya layanan', 'HPP', 'Laba kotor', 'Jumlah transaksi', 'Jumlah retur'];
        $ambil = fn (array $baris, array $kolom): array => array_values(array_map(fn (array $b): array => array_values(array_map(fn (string $k): string|int|null => self::Sel($b[$k] ?? null), $kolom)), $baris));

        return match ($tab) {
            'produk' => [['Produk', 'Qty (satuan dasar)', ...$judulAngka], $ambil($this->agregat->PerProduk($saring, $cari), ['NamaProduk', 'Qty', ...$angka])],
            'kategori' => [['Kategori', 'Jumlah produk', 'Qty (satuan dasar)', 'Kotor', 'Diskon', 'Retur', 'Bersih', 'Pajak', 'HPP', 'Laba kotor'], $ambil($this->PerKategori($saring), ['NamaKategori', 'JumlahProduk', 'Qty', 'Kotor', 'Diskon', 'Retur', 'Bersih', 'Pajak', 'Hpp', 'LabaKotor'])],
            'jam' => [['Hari', 'Jam', 'Bersih', 'Jumlah transaksi'], $ambil(array_map(fn (array $s): array => [...$s, 'Hari' => self::NamaHari($s['Hari']), 'Jam' => sprintf('%02d:00', $s['Jam'])], $this->PerJam($saring)['Sel']), ['Hari', 'Jam', 'Bersih', 'JumlahTransaksi'])],
            'kasir' => [['Kasir', ...$judulAngka, 'Rata-rata keranjang'], $ambil($this->PerKasir($saring), ['NamaKasir', ...$angka, 'RataRataKeranjang'])],
            'kanal' => [['Kanal', ...$judulAngka, 'Rata-rata keranjang'], $ambil($this->PerKanal($saring), ['LabelKanal', ...$angka, 'RataRataKeranjang'])],
            'metode' => [['Metode bayar', 'Jenis', 'Diterima', 'Refund', 'Bersih', 'Jumlah transaksi'], $ambil($this->PerMetode($saring), ['NamaMetode', 'LabelJenis', 'Diterima', 'Refund', 'Bersih', 'JumlahTransaksi'])],
            'diskon' => [['Kasir', 'Jumlah transaksi', 'Transaksi berdiskon', 'Diskon disetujui', 'Diskon baris', 'Diskon pesanan', 'Total diskon', 'Kotor'], $ambil($this->Diskon($saring), ['NamaKasir', 'JumlahTransaksi', 'JumlahBerdiskon', 'JumlahDisetujui', 'DiskonBaris', 'DiskonPesanan', 'TotalDiskon', 'Kotor'])],
            default => [['Tanggal', ...$judulAngka, 'Rata-rata keranjang'], $ambil($this->Harian($saring), ['Tanggal', ...$angka, 'RataRataKeranjang'])],
        };
    }

    /**
     * Kasir yang pernah bertransaksi di outlet yang boleh diakses, urut nama.
     *
     * @param  list<int>|null  $idOutletBoleh
     * @return list<array{Id: int, Nilai: string, Label: string}>
     */
    private function AmbilOpsiKasir(?array $idOutletBoleh): array
    {
        $opsi = array_map(fn (int $id, array $p): array => ['Id' => $id, 'Nilai' => $p['Uuid'], 'Label' => $p['Nama']], array_keys($nama = $this->anggota->AmbilNama($this->agregat->AmbilIdKasir($idOutletBoleh))), $nama);
        usort($opsi, fn (array $a, array $b): int => strcmp($a['Label'], $b['Label']));

        return $opsi;
    }

    public static function NamaHari(int $hari): string
    {
        return ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'][$hari - 1] ?? (string) $hari;
    }

    /**
     * @param  list<array<string, mixed>>  $baris
     * @return list<array<string, mixed>>
     */
    private static function UrutBersih(array $baris): array
    {
        usort($baris, fn (array $a, array $b): int => Uang::Dari(self::Teks($b['Bersih']))->Bandingkan(Uang::Dari(self::Teks($a['Bersih']))));

        return $baris;
    }

    private static function Sel(mixed $nilai): string|int|null
    {
        return is_string($nilai) || is_int($nilai) || $nilai === null ? $nilai : null;
    }

    private static function Teks(mixed $nilai): string
    {
        return is_string($nilai) ? $nilai : '0';
    }
}
