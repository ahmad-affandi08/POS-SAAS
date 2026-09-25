<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Penjualan\Data\DataAgregatPenjualan;
use App\Domain\Penjualan\Data\DataSaringLaporanPenjualan;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Enum\StatusPenjualan;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PenjualanDetail;
use App\Domain\Penjualan\Model\PenjualanPembayaran;
use App\Domain\Penjualan\Model\ReturPenjualan;
use App\Domain\Penjualan\Model\ReturPenjualanDetail;
use App\Domain\Penjualan\Model\ReturPenjualanPembayaran;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as KueriDasar;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Kueri publik agregasi penjualan untuk laporan & ringkasan harian (F-14a, "Rincian F-14a"). Semua dijumlah MySQL atas
 * kolom DECIMAL dan dibaca sebagai string desimal lalu `Uang`/`Kuantitas` (tanpa pecahan biner). Aturan angka:
 * - penjualan berstatus `Void` dikeluarkan dari tanggal bisnisnya; penjualan yang diretur tetap dihitung penuh pada
 *   tanggal jualnya, dan retur mengurangi pada tanggal bisnis, outlet, dan kasir returnya (kanal = kanal penjualan asal);
 * - `Kotor` = bruto − pajak inklusif = `Subtotal + DiskonBaris − TotalPajak + TotalPajakEksklusif`; per baris
 *   `Bruto − JumlahPajak + PajakEksklusif`; `Retur` = nilai retur − pajak − biaya layanan (sama dengan jurnal J-07/J-09);
 * - pembayaran per metode = uang diterima bersih kembalian (tunai) dikurangi refund retur per metode.
 * Dipakai domain Laporan (dasbor, laporan penjualan, `RingkasanPenjualanHarian`); tidak ada domain lain yang membaca
 * tabel penjualan langsung (CLAUDE.md #14).
 */
final class AgregatPenjualan
{
    private const KOTOR = '(`Penjualan`.`Subtotal` + `Penjualan`.`DiskonBaris` - `Penjualan`.`TotalPajak` + `Penjualan`.`TotalPajakEksklusif`)';

    private const RETUR = '(`ReturPenjualan`.`TotalNilai` - `ReturPenjualan`.`TotalPajak` - `ReturPenjualan`.`TotalBiayaLayanan`)';

    /** Dimensi pengelompokan: [kolom sisi penjualan, kolom sisi retur]. */
    private const DIMENSI = [
        'Tanggal' => ['`Penjualan`.`TanggalBisnis`', '`ReturPenjualan`.`TanggalBisnis`'],
        'Outlet' => ['`Penjualan`.`IdOutlet`', '`ReturPenjualan`.`IdOutlet`'],
        'Kasir' => ['`Penjualan`.`IdPengguna`', '`ReturPenjualan`.`IdPengguna`'],
        'Kanal' => ['`Penjualan`.`Kanal`', '`asal`.`Kanal`'],
    ];

    /** Alias kolom dimensi di hasil kueri. */
    private const ALIAS = ['`D0`', '`D1`', '`D2`', '`D3`'];

    /** Kolom urut laporan per produk (`TabelData` mode server). */
    public const KOLOM_URUT_PRODUK = ['NamaProduk', 'Qty', 'Kotor', 'Diskon', 'Retur', 'Bersih', 'Hpp', 'LabaKotor', 'JumlahTransaksi'];

    public const URUT_BAWAAN_PRODUK = '-Bersih';

    /**
     * Angka total untuk saring (tanpa pengelompokan).
     */
    public function Total(DataSaringLaporanPenjualan $saring): DataAgregatPenjualan
    {
        return $this->Agregasi($saring, [])['']['Agregat'] ?? DataAgregatPenjualan::Nol();
    }

    /**
     * Agregat per kombinasi dimensi. Kunci hasil = nilai dimensi digabung `|` (urutan sesuai `$dimensi`).
     *
     * @param  list<'Tanggal'|'Outlet'|'Kasir'|'Kanal'>  $dimensi
     * @return array<string, array{Kunci: list<string>, Agregat: DataAgregatPenjualan}>
     */
    public function Agregasi(DataSaringLaporanPenjualan $saring, array $dimensi): array
    {
        if ($saring->CekTanpaOutlet()) {
            return [];
        }

        $kolomJual = self::KolomDimensi($dimensi, 0);
        $kolomRetur = self::KolomDimensi($dimensi, 1);

        $jual = $this->KueriJual($saring)
            ->selectRaw(self::PilihDimensi($kolomJual))
            ->selectRaw('COALESCE(SUM('.self::KOTOR.'), 0) AS `Kotor`, COALESCE(SUM(`Penjualan`.`TotalDiskon`), 0) AS `Diskon`, COALESCE(SUM(`Penjualan`.`TotalPajak`), 0) AS `Pajak`, COALESCE(SUM(`Penjualan`.`BiayaLayanan`), 0) AS `Layanan`, COALESCE(SUM(`Penjualan`.`TotalHpp`), 0) AS `Hpp`, COUNT(*) AS `Jumlah`')
            ->when($kolomJual !== [], fn (Builder $k) => $k->groupByRaw(implode(', ', $kolomJual)))
            ->toBase()
            ->get();

        $retur = $this->KueriRetur($saring)
            ->selectRaw(self::PilihDimensi($kolomRetur))
            ->selectRaw('COALESCE(SUM('.self::RETUR.'), 0) AS `Retur`, COALESCE(SUM(`ReturPenjualan`.`TotalPajak`), 0) AS `Pajak`, COALESCE(SUM(`ReturPenjualan`.`TotalBiayaLayanan`), 0) AS `Layanan`, COALESCE(SUM(`ReturPenjualan`.`TotalHpp`), 0) AS `Hpp`, COUNT(*) AS `Jumlah`')
            ->when($kolomRetur !== [], fn (Builder $k) => $k->groupByRaw(implode(', ', $kolomRetur)))
            ->toBase()
            ->get();

        $hasil = [];
        $jumlahDimensi = count($dimensi);

        foreach ($jual as $b) {
            [$kunci, $nilai] = self::BacaKunci($b, $jumlahDimensi);

            if ((int) $b->Jumlah === 0) {
                continue;
            }

            $agregat = new DataAgregatPenjualan(
                Uang::Dari(self::Teks($b->Kotor)),
                Uang::Dari(self::Teks($b->Diskon)),
                Uang::Nol(),
                Uang::Dari(self::Teks($b->Pajak)),
                Uang::Dari(self::Teks($b->Layanan)),
                Uang::Dari(self::Teks($b->Hpp)),
                (int) $b->Jumlah,
            );
            $hasil[$kunci] = ['Kunci' => $nilai, 'Agregat' => isset($hasil[$kunci]) ? $hasil[$kunci]['Agregat']->Tambah($agregat) : $agregat];
        }

        foreach ($retur as $b) {
            [$kunci, $nilai] = self::BacaKunci($b, $jumlahDimensi);

            if ((int) $b->Jumlah === 0) {
                continue;
            }

            $agregat = new DataAgregatPenjualan(
                Uang::Nol(),
                Uang::Nol(),
                Uang::Dari(self::Teks($b->Retur)),
                Uang::Nol()->Kurangi(Uang::Dari(self::Teks($b->Pajak))),
                Uang::Nol()->Kurangi(Uang::Dari(self::Teks($b->Layanan))),
                Uang::Nol()->Kurangi(Uang::Dari(self::Teks($b->Hpp))),
                0,
                (int) $b->Jumlah,
            );
            $hasil[$kunci] = ['Kunci' => $nilai, 'Agregat' => isset($hasil[$kunci]) ? $hasil[$kunci]['Agregat']->Tambah($agregat) : $agregat];
        }

        return $hasil;
    }

    /**
     * Bahan satu baris `RingkasanPenjualanHarian` per (outlet, tanggal bisnis): agregat, jumlah penjualan void pada
     * tanggal jualnya, uang per metode bayar, dan angka per kanal. Pasangan tanpa dokumen apa pun tidak ikut.
     *
     * @return list<array{IdOutlet: int, TanggalBisnis: string, Agregat: DataAgregatPenjualan, JumlahVoid: int, PerMetodeBayar: list<array{IdMetodePembayaran: int, Jenis: string, Nama: string, Jumlah: string}>, PerKanal: list<array{Kanal: string, Bersih: string, JumlahTransaksi: int}>}>
     */
    public function Harian(DataSaringLaporanPenjualan $saring): array
    {
        if ($saring->CekTanpaOutlet()) {
            return [];
        }

        $agregat = $this->Agregasi($saring, ['Outlet', 'Tanggal']);
        $void = $this->KueriDasarJual($saring)
            ->where('Penjualan.Status', StatusPenjualan::Void->value)
            ->selectRaw('`Penjualan`.`IdOutlet` AS `D0`, `Penjualan`.`TanggalBisnis` AS `D1`, COUNT(*) AS `Jumlah`')
            ->groupBy('Penjualan.IdOutlet', 'Penjualan.TanggalBisnis')
            ->toBase()
            ->get();
        $jumlahVoid = [];

        foreach ($void as $b) {
            $jumlahVoid[self::Teks($b->D0).'|'.self::Teks($b->D1)] = (int) $b->Jumlah;
        }

        $metode = [];

        foreach ($this->PerMetode($saring, ['Outlet', 'Tanggal']) as $baris) {
            $metode[implode('|', $baris['Kunci'])][] = [
                'IdMetodePembayaran' => $baris['IdMetodePembayaran'],
                'Jenis' => $baris['Jenis'],
                'Nama' => $baris['Nama'],
                'Jumlah' => $baris['Bersih'],
            ];
        }

        $kanal = [];

        foreach ($this->Agregasi($saring, ['Outlet', 'Tanggal', 'Kanal']) as $baris) {
            [$idOutlet, $tanggal, $namaKanal] = $baris['Kunci'];
            $kanal["{$idOutlet}|{$tanggal}"][] = [
                'Kanal' => $namaKanal,
                'Bersih' => $baris['Agregat']->Bersih()->KeString(),
                'JumlahTransaksi' => $baris['Agregat']->jumlahTransaksi,
            ];
        }

        $semuaKunci = array_values(array_unique([...array_keys($agregat), ...array_keys($jumlahVoid), ...array_keys($metode)]));
        sort($semuaKunci);
        $hasil = [];

        foreach ($semuaKunci as $kunci) {
            [$idOutlet, $tanggal] = explode('|', $kunci, 2);
            $hasil[] = [
                'IdOutlet' => (int) $idOutlet,
                'TanggalBisnis' => $tanggal,
                'Agregat' => $agregat[$kunci]['Agregat'] ?? DataAgregatPenjualan::Nol(),
                'JumlahVoid' => $jumlahVoid[$kunci] ?? 0,
                'PerMetodeBayar' => $metode[$kunci] ?? [],
                'PerKanal' => $kanal[$kunci] ?? [],
            ];
        }

        return $hasil;
    }

    /**
     * Uang per metode bayar: diterima (bersih kembalian untuk tunai), refund retur (pada tanggal/outlet/kasir retur),
     * dan bersih = diterima − refund. Urut bersih menurun.
     *
     * @param  list<'Tanggal'|'Outlet'|'Kasir'|'Kanal'>  $dimensi
     * @return list<array{Kunci: list<string>, IdMetodePembayaran: int, Jenis: string, Nama: string, Diterima: string, Refund: string, Bersih: string, JumlahTransaksi: int}>
     */
    public function PerMetode(DataSaringLaporanPenjualan $saring, array $dimensi = []): array
    {
        if ($saring->CekTanpaOutlet()) {
            return [];
        }

        $kolomJual = self::KolomDimensi($dimensi, 0);
        $kolomRetur = self::KolomDimensi($dimensi, 1);
        $grupJual = [...$kolomJual, '`PenjualanPembayaran`.`IdMetodePembayaran`'];
        $grupRetur = [...$kolomRetur, '`ReturPenjualanPembayaran`.`IdMetodePembayaran`'];

        $diterima = PenjualanPembayaran::query()
            ->join('Penjualan', fn (JoinClause $j) => $j->on('Penjualan.Id', '=', 'PenjualanPembayaran.IdPenjualan')->on('Penjualan.IdTenant', '=', 'PenjualanPembayaran.IdTenant'))
            ->where('Penjualan.Status', '!=', StatusPenjualan::Void->value);
        $this->TerapkanSaringJual($diterima, $saring);
        $diterima = $diterima
            ->selectRaw(self::PilihDimensi($kolomJual))
            ->selectRaw('`PenjualanPembayaran`.`IdMetodePembayaran` AS `IdMetode`, MAX(`PenjualanPembayaran`.`JenisMetode`) AS `Jenis`, MAX(`PenjualanPembayaran`.`NamaMetode`) AS `Nama`')
            ->selectRaw('COALESCE(SUM(`PenjualanPembayaran`.`Jumlah` - CASE WHEN `PenjualanPembayaran`.`JenisMetode` = ? THEN `Penjualan`.`Kembalian` ELSE 0 END), 0) AS `Jumlah`', [JenisMetodePembayaran::Tunai->value])
            ->selectRaw('COUNT(DISTINCT `Penjualan`.`Id`) AS `JumlahTransaksi`')
            ->groupByRaw(implode(', ', $grupJual))
            ->toBase()
            ->get();

        $refund = ReturPenjualanPembayaran::query()
            ->join('ReturPenjualan', fn (JoinClause $j) => $j->on('ReturPenjualan.Id', '=', 'ReturPenjualanPembayaran.IdReturPenjualan')->on('ReturPenjualan.IdTenant', '=', 'ReturPenjualanPembayaran.IdTenant'))
            ->join('Penjualan as asal', fn (JoinClause $j) => $j->on('asal.Id', '=', 'ReturPenjualan.IdPenjualanAsal')->on('asal.IdTenant', '=', 'ReturPenjualan.IdTenant'));
        $this->TerapkanSaringRetur($refund, $saring);
        $refund = $refund
            ->selectRaw(self::PilihDimensi($kolomRetur))
            ->selectRaw('`ReturPenjualanPembayaran`.`IdMetodePembayaran` AS `IdMetode`, MAX(`ReturPenjualanPembayaran`.`JenisMetode`) AS `Jenis`, MAX(`ReturPenjualanPembayaran`.`NamaMetode`) AS `Nama`, COALESCE(SUM(`ReturPenjualanPembayaran`.`Jumlah`), 0) AS `Jumlah`')
            ->groupByRaw(implode(', ', $grupRetur))
            ->toBase()
            ->get();

        $jumlahDimensi = count($dimensi);
        $hasil = [];

        foreach ([[$diterima, true], [$refund, false]] as [$daftar, $masuk]) {
            foreach ($daftar as $b) {
                [$kunci, $nilai] = self::BacaKunci($b, $jumlahDimensi);
                $kunciMetode = $kunci.'#'.self::Teks($b->IdMetode);
                $ada = $hasil[$kunciMetode] ?? [
                    'Kunci' => $nilai,
                    'IdMetodePembayaran' => (int) $b->IdMetode,
                    'Jenis' => self::Teks($b->Jenis),
                    'Nama' => self::Teks($b->Nama),
                    'Diterima' => Uang::Nol(),
                    'Refund' => Uang::Nol(),
                    'JumlahTransaksi' => 0,
                ];
                $jumlah = Uang::Dari(self::Teks($b->Jumlah));

                if ($masuk) {
                    $ada['Diterima'] = $ada['Diterima']->Tambah($jumlah);
                    $ada['JumlahTransaksi'] += (int) $b->JumlahTransaksi;
                    $ada['Nama'] = self::Teks($b->Nama);
                } else {
                    $ada['Refund'] = $ada['Refund']->Tambah($jumlah);
                }

                $hasil[$kunciMetode] = $ada;
            }
        }

        $keluaran = array_map(fn (array $b): array => [
            ...$b,
            'Diterima' => $b['Diterima']->KeString(),
            'Refund' => $b['Refund']->KeString(),
            'Bersih' => $b['Diterima']->Kurangi($b['Refund'])->KeString(),
        ], array_values($hasil));
        usort($keluaran, fn (array $a, array $b): int => Uang::Dari($b['Bersih'])->Bandingkan(Uang::Dari($a['Bersih'])) ?: strcmp($a['Nama'], $b['Nama']));

        return $keluaran;
    }

    /**
     * Laporan per produk untuk `TabelData` mode server: cari nama produk (snapshot di baris), urut kolom angka.
     *
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function PerProdukTabel(DataSaringLaporanPenjualan $saring, DataPermintaanTabel $tabel): array
    {
        $grup = $this->KueriProduk($saring, $tabel->cari);
        $total = DB::query()->fromSub($grup, 'Grup')->count();
        $jumlahHalaman = max(1, intdiv($total + $tabel->perHalaman - 1, $tabel->perHalaman));
        $halaman = min($tabel->halaman, $jumlahHalaman);

        foreach ($tabel->urut as $urut) {
            if (in_array($urut['Kolom'], self::KOLOM_URUT_PRODUK, true)) {
                $grup->orderBy($urut['Kolom'], $urut['Turun'] ? 'desc' : 'asc');
            }
        }

        $baris = $grup->orderBy('IdProduk')->offset(($halaman - 1) * $tabel->perHalaman)->limit($tabel->perHalaman)->get();

        return [
            'Data' => array_values(array_map(fn (stdClass $b): array => self::PetakanProduk($b), $baris->all())),
            'Meta' => ['Halaman' => $halaman, 'PerHalaman' => $tabel->perHalaman, 'Total' => $total, 'JumlahHalaman' => $jumlahHalaman],
        ];
    }

    /**
     * Semua baris per produk (ekspor CSV, pengelompokan per kategori, produk terlaris), urut `$urut` lalu Id produk.
     *
     * @return list<array{IdProduk: int, NamaProduk: string, Qty: string, Kotor: string, Diskon: string, Retur: string, Bersih: string, Pajak: string, BiayaLayanan: string, Hpp: string, LabaKotor: string, JumlahTransaksi: int, JumlahRetur: int}>
     */
    public function PerProduk(DataSaringLaporanPenjualan $saring, string $cari = '', string $urut = '-Bersih', ?int $batas = null): array
    {
        $kolom = ltrim($urut, '-');
        $grup = $this->KueriProduk($saring, $cari)
            ->when(in_array($kolom, self::KOLOM_URUT_PRODUK, true), fn (KueriDasar $k) => $k->orderBy($kolom, str_starts_with($urut, '-') ? 'desc' : 'asc'))
            ->orderBy('IdProduk')
            ->when($batas !== null, fn (KueriDasar $k) => $k->limit((int) $batas));

        return array_values(array_map(fn (stdClass $b): array => self::PetakanProduk($b), $grup->get()->all()));
    }

    /**
     * Penjualan per hari-dalam-minggu × jam lokal outlet (heatmap). `offsetDetik` = selisih zona waktu outlet dari UTC
     * (zona Indonesia tanpa musim panas); outlet yang tidak ada di peta memakai `offsetBawaan`. `Hari` 1 = Senin.
     *
     * @param  array<int, int>  $offsetDetik
     * @return list<array{Hari: int, Jam: int, Bersih: string, JumlahTransaksi: int}>
     */
    public function PerJam(DataSaringLaporanPenjualan $saring, array $offsetDetik, int $offsetBawaan = 25200): array
    {
        if ($saring->CekTanpaOutlet()) {
            return [];
        }

        $kasus = '';
        $ikatan = [];

        foreach ($offsetDetik as $idOutlet => $offset) {
            $kasus .= ' WHEN ? THEN ?';
            $ikatan[] = $idOutlet;
            $ikatan[] = $offset;
        }

        $geser = $kasus === '' ? '?' : "CASE `Penjualan`.`IdOutlet`{$kasus} ELSE ? END";
        $ikatan[] = $offsetBawaan;
        $lokal = "DATE_ADD(`Penjualan`.`DibuatOfflinePada`, INTERVAL ({$geser}) SECOND)";

        $baris = $this->KueriJual($saring)
            ->selectRaw("WEEKDAY({$lokal}) + 1 AS `Hari`, HOUR({$lokal}) AS `Jam`", [...$ikatan, ...$ikatan])
            ->selectRaw('COALESCE(SUM('.self::KOTOR.' - `Penjualan`.`TotalDiskon`), 0) AS `Bersih`, COUNT(*) AS `Jumlah`')
            ->groupByRaw('`Hari`, `Jam`')
            ->orderByRaw('`Hari`, `Jam`')
            ->toBase()
            ->get();

        return array_values(array_map(fn (object $b): array => [
            'Hari' => (int) $b->Hari,
            'Jam' => (int) $b->Jam,
            'Bersih' => Uang::Dari(self::Teks($b->Bersih))->KeString(),
            'JumlahTransaksi' => (int) $b->Jumlah,
        ], $baris->all()));
    }

    /**
     * Diskon per kasir (penjualan bukan void): jumlah transaksi & yang berdiskon, diskon baris, diskon pesanan, total,
     * kotor, dan jumlah diskon yang disetujui penyetuju (BR-07.3). Urut total diskon menurun.
     *
     * @return list<array{IdKasir: int, JumlahTransaksi: int, JumlahBerdiskon: int, JumlahDisetujui: int, DiskonBaris: string, DiskonPesanan: string, TotalDiskon: string, Kotor: string}>
     */
    public function DiskonPerKasir(DataSaringLaporanPenjualan $saring): array
    {
        if ($saring->CekTanpaOutlet()) {
            return [];
        }

        $baris = $this->KueriJual($saring)
            ->selectRaw('`Penjualan`.`IdPengguna` AS `IdKasir`, COUNT(*) AS `Jumlah`')
            ->selectRaw('SUM(CASE WHEN `Penjualan`.`TotalDiskon` > 0 THEN 1 ELSE 0 END) AS `Berdiskon`')
            ->selectRaw('SUM(CASE WHEN `Penjualan`.`IdPenyetujuDiskon` IS NOT NULL THEN 1 ELSE 0 END) AS `Disetujui`')
            ->selectRaw('COALESCE(SUM(`Penjualan`.`DiskonBaris`), 0) AS `DiskonBaris`, COALESCE(SUM(`Penjualan`.`DiskonPesanan`), 0) AS `DiskonPesanan`, COALESCE(SUM(`Penjualan`.`TotalDiskon`), 0) AS `TotalDiskon`')
            ->selectRaw('COALESCE(SUM('.self::KOTOR.'), 0) AS `Kotor`')
            ->groupBy('Penjualan.IdPengguna')
            ->orderByRaw('`TotalDiskon` DESC, `IdKasir`')
            ->toBase()
            ->get();

        return array_values(array_map(fn (object $b): array => [
            'IdKasir' => (int) $b->IdKasir,
            'JumlahTransaksi' => (int) $b->Jumlah,
            'JumlahBerdiskon' => (int) $b->Berdiskon,
            'JumlahDisetujui' => (int) $b->Disetujui,
            'DiskonBaris' => Uang::Dari(self::Teks($b->DiskonBaris))->KeString(),
            'DiskonPesanan' => Uang::Dari(self::Teks($b->DiskonPesanan))->KeString(),
            'TotalDiskon' => Uang::Dari(self::Teks($b->TotalDiskon))->KeString(),
            'Kotor' => Uang::Dari(self::Teks($b->Kotor))->KeString(),
        ], $baris->all()));
    }

    /**
     * Jumlah penjualan yang ditandai `PerluTinjauan` (dasbor).
     *
     * @param  list<int>|null  $idOutlet  null = semua outlet
     */
    public function HitungPerluTinjauan(?array $idOutlet): int
    {
        return Penjualan::query()
            ->where('PerluTinjauan', true)
            ->when($idOutlet !== null, fn (Builder $k) => $k->whereIn('IdOutlet', $idOutlet ?? []))
            ->count();
    }

    /**
     * F-15 tutup harian: jumlah penjualan `PerluTinjauan` per outlet per tanggal bisnis dalam rentang, kunci
     * `"{IdOutlet}|{Y-m-d}"`.
     *
     * @param  list<int>|null  $idOutlet  null = semua outlet
     * @return array<string, int>
     */
    public function HitungPerluTinjauanPerHari(?array $idOutlet, CarbonInterface $dari, CarbonInterface $sampai): array
    {
        $hasil = [];

        foreach (Penjualan::query()
            ->where('PerluTinjauan', true)
            ->whereBetween('TanggalBisnis', [$dari->toDateString(), $sampai->toDateString()])
            ->when($idOutlet !== null, fn (Builder $k) => $k->whereIn('IdOutlet', $idOutlet ?? []))
            ->groupBy('IdOutlet', 'TanggalBisnis')
            ->toBase()
            ->get(['IdOutlet', 'TanggalBisnis', DB::raw('COUNT(*) AS Jumlah')]) as $b) {
            $hasil[$b->IdOutlet.'|'.substr((string) $b->TanggalBisnis, 0, 10)] = (int) $b->Jumlah;
        }

        return $hasil;
    }

    /**
     * Id kasir yang pernah membuat penjualan atau retur di outlet ini (opsi saring laporan), paling banyak 500.
     *
     * @param  list<int>|null  $idOutlet
     * @return list<int>
     */
    public function AmbilIdKasir(?array $idOutlet): array
    {
        $jual = Penjualan::query()
            ->when($idOutlet !== null, fn (Builder $k) => $k->whereIn('IdOutlet', $idOutlet ?? []))
            ->distinct()
            ->limit(500)
            ->pluck('IdPengguna')
            ->all();
        $retur = ReturPenjualan::query()
            ->when($idOutlet !== null, fn (Builder $k) => $k->whereIn('IdOutlet', $idOutlet ?? []))
            ->distinct()
            ->limit(500)
            ->pluck('IdPengguna')
            ->all();

        return array_slice(array_values(array_unique(array_map('intval', [...$jual, ...$retur]))), 0, 500);
    }

    /**
     * Subkueri per produk: gabungan baris penjualan (bukan void) dan baris retur (nilai negatif) lalu dijumlah per
     * produk. Kolom: IdProduk, NamaProduk, Qty (satuan dasar), Kotor, Diskon, Retur, Bersih, Pajak, BiayaLayanan, Hpp,
     * LabaKotor, JumlahTransaksi, JumlahRetur.
     */
    private function KueriProduk(DataSaringLaporanPenjualan $saring, string $cari): KueriDasar
    {
        $pola = PenerapKueriTabel::PolaCari($cari);

        $jual = PenjualanDetail::query()
            ->join('Penjualan', fn (JoinClause $j) => $j->on('Penjualan.Id', '=', 'PenjualanDetail.IdPenjualan')->on('Penjualan.IdTenant', '=', 'PenjualanDetail.IdTenant'))
            ->where('Penjualan.Status', '!=', StatusPenjualan::Void->value)
            ->when($cari !== '', fn (Builder $k) => $k->where('PenjualanDetail.NamaProduk', 'like', $pola));
        $this->TerapkanSaringJual($jual, $saring);
        $jual = $jual->selectRaw(
            '`PenjualanDetail`.`IdProduk` AS `IdProduk`, `PenjualanDetail`.`NamaProduk` AS `NamaProduk`, `PenjualanDetail`.`JumlahDasar` AS `Qty`, '
            .'(`PenjualanDetail`.`Bruto` - `PenjualanDetail`.`JumlahPajak` + `PenjualanDetail`.`PajakEksklusif`) AS `Kotor`, '
            .'(`PenjualanDetail`.`JumlahDiskon` + `PenjualanDetail`.`JumlahDiskonPesanan`) AS `Diskon`, 0 AS `Retur`, '
            .'`PenjualanDetail`.`JumlahPajak` AS `Pajak`, `PenjualanDetail`.`BiayaLayanan` AS `Layanan`, `PenjualanDetail`.`TotalHpp` AS `Hpp`, '
            .'`Penjualan`.`Id` AS `IdPenjualan`, NULL AS `IdRetur`'
        )->toBase();

        $retur = ReturPenjualanDetail::query()
            ->join('ReturPenjualan', fn (JoinClause $j) => $j->on('ReturPenjualan.Id', '=', 'ReturPenjualanDetail.IdReturPenjualan')->on('ReturPenjualan.IdTenant', '=', 'ReturPenjualanDetail.IdTenant'))
            ->join('Penjualan as asal', fn (JoinClause $j) => $j->on('asal.Id', '=', 'ReturPenjualan.IdPenjualanAsal')->on('asal.IdTenant', '=', 'ReturPenjualan.IdTenant'))
            ->when($cari !== '', fn (Builder $k) => $k->where('ReturPenjualanDetail.NamaProduk', 'like', $pola));
        $this->TerapkanSaringRetur($retur, $saring);
        $retur = $retur->selectRaw(
            '`ReturPenjualanDetail`.`IdProduk`, `ReturPenjualanDetail`.`NamaProduk`, 0 - `ReturPenjualanDetail`.`JumlahDasar`, 0, 0, '
            .'(`ReturPenjualanDetail`.`NilaiBaris` - `ReturPenjualanDetail`.`Pajak` - `ReturPenjualanDetail`.`BiayaLayanan`), '
            .'0 - `ReturPenjualanDetail`.`Pajak`, 0 - `ReturPenjualanDetail`.`BiayaLayanan`, 0 - `ReturPenjualanDetail`.`TotalHpp`, NULL, `ReturPenjualan`.`Id`'
        )->toBase();

        if ($saring->CekTanpaOutlet()) {
            $jual->whereRaw('1 = 0');
            $retur->whereRaw('1 = 0');
        }

        return DB::query()
            ->fromSub($jual->unionAll($retur), 'Baris')
            ->groupBy('IdProduk')
            ->selectRaw('`IdProduk`, MAX(`NamaProduk`) AS `NamaProduk`, SUM(`Qty`) AS `Qty`, SUM(`Kotor`) AS `Kotor`, SUM(`Diskon`) AS `Diskon`, SUM(`Retur`) AS `Retur`')
            ->selectRaw('SUM(`Kotor`) - SUM(`Diskon`) - SUM(`Retur`) AS `Bersih`, SUM(`Pajak`) AS `Pajak`, SUM(`Layanan`) AS `BiayaLayanan`, SUM(`Hpp`) AS `Hpp`')
            ->selectRaw('SUM(`Kotor`) - SUM(`Diskon`) - SUM(`Retur`) - SUM(`Hpp`) AS `LabaKotor`, COUNT(DISTINCT `IdPenjualan`) AS `JumlahTransaksi`, COUNT(DISTINCT `IdRetur`) AS `JumlahRetur`');
    }

    /**
     * @return array{IdProduk: int, NamaProduk: string, Qty: string, Kotor: string, Diskon: string, Retur: string, Bersih: string, Pajak: string, BiayaLayanan: string, Hpp: string, LabaKotor: string, JumlahTransaksi: int, JumlahRetur: int}
     */
    private static function PetakanProduk(stdClass $b): array
    {
        return [
            'IdProduk' => (int) $b->IdProduk,
            'NamaProduk' => self::Teks($b->NamaProduk),
            'Qty' => Kuantitas::Dari(self::Teks($b->Qty))->KeString(),
            'Kotor' => Uang::Dari(self::Teks($b->Kotor))->KeString(),
            'Diskon' => Uang::Dari(self::Teks($b->Diskon))->KeString(),
            'Retur' => Uang::Dari(self::Teks($b->Retur))->KeString(),
            'Bersih' => Uang::Dari(self::Teks($b->Bersih))->KeString(),
            'Pajak' => Uang::Dari(self::Teks($b->Pajak))->KeString(),
            'BiayaLayanan' => Uang::Dari(self::Teks($b->BiayaLayanan))->KeString(),
            'Hpp' => Uang::Dari(self::Teks($b->Hpp))->KeString(),
            'LabaKotor' => Uang::Dari(self::Teks($b->LabaKotor))->KeString(),
            'JumlahTransaksi' => (int) $b->JumlahTransaksi,
            'JumlahRetur' => (int) $b->JumlahRetur,
        ];
    }

    /**
     * Penjualan bukan void sesuai saring.
     *
     * @return Builder<Penjualan>
     */
    private function KueriJual(DataSaringLaporanPenjualan $saring): Builder
    {
        return $this->KueriDasarJual($saring)->where('Penjualan.Status', '!=', StatusPenjualan::Void->value);
    }

    /**
     * @return Builder<Penjualan>
     */
    private function KueriDasarJual(DataSaringLaporanPenjualan $saring): Builder
    {
        $kueri = Penjualan::query();
        $this->TerapkanSaringJual($kueri, $saring);

        return $kueri;
    }

    /**
     * Retur sesuai saring (bergabung dengan penjualan asal sebagai `asal` untuk kanal).
     *
     * @return Builder<ReturPenjualan>
     */
    private function KueriRetur(DataSaringLaporanPenjualan $saring): Builder
    {
        $kueri = ReturPenjualan::query()
            ->join('Penjualan as asal', fn (JoinClause $j) => $j->on('asal.Id', '=', 'ReturPenjualan.IdPenjualanAsal')->on('asal.IdTenant', '=', 'ReturPenjualan.IdTenant'));
        $this->TerapkanSaringRetur($kueri, $saring);

        return $kueri;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $kueri
     */
    private function TerapkanSaringJual(Builder $kueri, DataSaringLaporanPenjualan $saring): void
    {
        $kueri->whereBetween('Penjualan.TanggalBisnis', [$saring->dari->toDateString(), $saring->sampai->toDateString()])
            ->when($saring->idOutlet !== null, fn (Builder $k) => $k->whereIn('Penjualan.IdOutlet', $saring->idOutlet ?? []))
            ->when($saring->idKasir !== null, fn (Builder $k) => $k->where('Penjualan.IdPengguna', $saring->idKasir))
            ->when($saring->kanal !== null, fn (Builder $k) => $k->where('Penjualan.Kanal', $saring->kanal?->value));
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $kueri
     */
    private function TerapkanSaringRetur(Builder $kueri, DataSaringLaporanPenjualan $saring): void
    {
        $kueri->whereBetween('ReturPenjualan.TanggalBisnis', [$saring->dari->toDateString(), $saring->sampai->toDateString()])
            ->when($saring->idOutlet !== null, fn (Builder $k) => $k->whereIn('ReturPenjualan.IdOutlet', $saring->idOutlet ?? []))
            ->when($saring->idKasir !== null, fn (Builder $k) => $k->where('ReturPenjualan.IdPengguna', $saring->idKasir))
            ->when($saring->kanal !== null, fn (Builder $k) => $k->where('asal.Kanal', $saring->kanal?->value));
    }

    /**
     * Kolom SQL dimensi untuk sisi penjualan (0) atau retur (1).
     *
     * @param  list<'Tanggal'|'Outlet'|'Kasir'|'Kanal'>  $dimensi
     * @param  0|1  $sisi
     * @return list<literal-string>
     */
    private static function KolomDimensi(array $dimensi, int $sisi): array
    {
        $hasil = [];

        foreach ($dimensi as $d) {
            $hasil[] = self::DIMENSI[$d][$sisi];
        }

        return $hasil;
    }

    /**
     * Daftar pilih kolom dimensi beralias `D0`, `D1`, ... (tanpa dimensi = satu kolom kosong).
     *
     * @param  list<literal-string>  $kolom
     * @return literal-string
     */
    private static function PilihDimensi(array $kolom): string
    {
        if ($kolom === []) {
            return "'' AS `D0`";
        }

        $bagian = [];

        foreach ($kolom as $i => $k) {
            $bagian[] = $k.' AS '.self::ALIAS[$i];
        }

        return implode(', ', $bagian);
    }

    /**
     * @return array{0: string, 1: list<string>}
     */
    private static function BacaKunci(object $baris, int $jumlahDimensi): array
    {
        $nilai = [];

        for ($i = 0; $i < $jumlahDimensi; $i++) {
            $nilai[] = self::Teks($baris->{"D{$i}"} ?? '');
        }

        return [implode('|', $nilai), $nilai];
    }

    private static function Teks(mixed $nilai): string
    {
        return is_string($nilai) || is_int($nilai) ? (string) $nilai : '0';
    }
}
