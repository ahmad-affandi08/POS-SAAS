<?php

declare(strict_types=1);

namespace Tests\Pendukung\Pembelian;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Katalog\Model\Satuan;
use App\Domain\Organisasi\Model\Gudang;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Pembelian\Aksi\AjukanPesananPembelian;
use App\Domain\Pembelian\Aksi\SimpanFakturPembelian;
use App\Domain\Pembelian\Aksi\SimpanPemasok;
use App\Domain\Pembelian\Aksi\SimpanPesananPembelian;
use App\Domain\Pembelian\Aksi\TerimaBarang;
use App\Domain\Pembelian\Data\DataBarisFakturPembelian;
use App\Domain\Pembelian\Data\DataBarisPenerimaanBarang;
use App\Domain\Pembelian\Data\DataBarisPesananPembelian;
use App\Domain\Pembelian\Data\DataFakturPembelian;
use App\Domain\Pembelian\Data\DataPemasok;
use App\Domain\Pembelian\Data\DataPenerimaanBarang;
use App\Domain\Pembelian\Data\DataPesananPembelian;
use App\Domain\Pembelian\Model\FakturPembelian;
use App\Domain\Pembelian\Model\Pemasok;
use App\Domain\Pembelian\Model\PenerimaanBarang;
use App\Domain\Pembelian\Model\PesananPembelian;
use App\Domain\Persediaan\Enum\MetodeHpp;
use App\Domain\Tenant\Model\Tenant;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\PanduanAwal\BantuanPanduanAwal;
use Tests\Pendukung\Persediaan\BantuanPersediaan;
use Tests\Pendukung\Persediaan\BantuanStokAwal;
use Tests\Pendukung\Persediaan\PemeriksaInvarian;

/**
 * Prasyarat & pintasan test F-04 fase 1 (pembelian): tenant ber-COA template (peran HutangUsaha, GRNI, PpnMasukan,
 * SelisihHpp terpetakan), tarif PPN terbit, pemasok, produk dengan satuan dus, dan dokumen lewat Aksi. Pemeriksa
 * invarian tambahan (saldo akun hutang = Σ sisa faktur, GRNI = Σ nilai GRN belum difakturkan) dengan SQL mentah.
 * Panggil `BantuanPendaftaran::SiapkanPrasyarat()` dulu.
 */
final class BantuanPembelian
{
    private static int $urutan = 0;

    /**
     * @return array{Tenant: Tenant, Pemilik: Pengguna, Outlet: Outlet, Gudang: Gudang, Pcs: Satuan, Kg: Satuan, Dus: Satuan}
     */
    public static function SiapkanTenant(MetodeHpp $metode = MetodeHpp::RataRata, bool $stokBolehMinus = false, string $namaUsaha = 'Toko Grosir Sembako Makmur Jaya'): array
    {
        $t = BantuanPersediaan::SiapkanTenant($namaUsaha, $metode, $stokBolehMinus);
        BantuanPanduanAwal::TerbitkanTarif('Ppn', null, '12.000000');

        return $t + ['Dus' => BantuanKatalog::BuatSatuan('Dus', 'dus')];
    }

    public static function BuatPemasok(string $nama = 'PT Sumber Pangan Nusantara', bool $pkp = false, int $terminHari = 30, ?int $idPengguna = null): Pemasok
    {
        self::$urutan++;

        return app(SimpanPemasok::class)->Jalankan(new DataPemasok(
            'SUP-'.str_pad((string) self::$urutan, 3, '0', STR_PAD_LEFT),
            $nama,
            'Bu Ratna Kusumawati',
            '081234567890',
            'penjualan@sumberpangan.co.id',
            'Jl. Raya Solo–Sragen Km 7, Karanganyar',
            $pkp ? '01.234.567.8-526.000' : null,
            $pkp,
            $terminHari,
            'BCA',
            '0152233445',
            $nama,
            null,
            $idPengguna,
        ));
    }

    /** Satuan pembelian tambahan (misal dus isi 12) untuk produk. */
    public static function TambahSatuan(Produk $produk, Satuan $satuan, string $konversi, bool $defaultBeli = true): ProdukSatuan
    {
        return ProdukSatuan::query()->create(['IdProduk' => $produk->Id, 'IdSatuan' => $satuan->Id, 'KonversiKeDasar' => $konversi, 'DefaultBeli' => $defaultBeli]);
    }

    public static function Hari(int $mundur = 0): CarbonImmutable
    {
        // Tanggal bisnis outlet uji (WIB, jam tutup buku bawaan 04:00), bukan tanggal kalender WIB: antara 00.00–04.00 WIB
        // tanggal kalender sudah maju sehari sehingga dianggap tanggal masa depan.
        return CarbonImmutable::now('Asia/Jakarta')->subHours(4)->startOfDay()->subDays($mundur);
    }

    /**
     * PO draf. `baris` = list [Produk, jumlah, harga, diskon?, ProdukSatuan?].
     *
     * @param  list<array{0: Produk, 1: string, 2: string, 3?: string, 4?: ProdukSatuan|null}>  $baris
     */
    public static function BuatPo(Pemasok $pemasok, Gudang $gudang, array $baris, int $idPengguna, string $ongkir = '0', ?CarbonImmutable $tanggal = null): PesananPembelian
    {
        return app(SimpanPesananPembelian::class)->Jalankan(new DataPesananPembelian(
            $pemasok->Uuid,
            $gudang->Id,
            $tanggal ?? self::Hari(),
            null,
            null,
            Uang::Dari($ongkir),
            null,
            array_map(fn (array $b): DataBarisPesananPembelian => new DataBarisPesananPembelian($b[0]->Uuid, isset($b[4]) ? $b[4]->Uuid : null, Kuantitas::Dari($b[1]), Uang::Dari($b[2]), Uang::Dari($b[3] ?? '0')), $baris),
            $idPengguna,
        ));
    }

    /**
     * PO disetujui langsung (total di bawah batas persetujuan).
     *
     * @param  list<array{0: Produk, 1: string, 2: string, 3?: string, 4?: ProdukSatuan|null}>  $baris
     */
    public static function BuatPoDisetujui(Pemasok $pemasok, Gudang $gudang, array $baris, int $idPengguna, string $ongkir = '0'): PesananPembelian
    {
        return app(AjukanPesananPembelian::class)->Jalankan(self::BuatPo($pemasok, $gudang, $baris, $idPengguna, $ongkir), $idPengguna);
    }

    /**
     * GRN dari PO: `jumlah` per baris PO berurutan (satuan pembelian PO); `pelacakan` per indeks: ['Batch' => nomor]
     * atau ['Seri' => list nomor].
     *
     * @param  list<string>  $jumlah
     * @param  array<int, array{Batch?: string, Kedaluwarsa?: string, Seri?: list<string>}>  $pelacakan
     */
    public static function TerimaDariPo(PesananPembelian $po, array $jumlah, int $idPengguna, string $ongkir = '0', ?CarbonImmutable $tanggal = null, array $pelacakan = []): PenerimaanBarang
    {
        $detail = $po->Detail()->get()->values();
        $baris = [];

        foreach ($jumlah as $i => $q) {
            $barisPo = $detail->get($i) ?? throw new \InvalidArgumentException("Baris PO ke-{$i} tidak ada.");
            $baris[] = new DataBarisPenerimaanBarang(
                $barisPo->Id,
                null,
                null,
                Kuantitas::Dari($q),
                null,
                null,
                $pelacakan[$i]['Batch'] ?? null,
                isset($pelacakan[$i]['Kedaluwarsa']) ? CarbonImmutable::parse($pelacakan[$i]['Kedaluwarsa']) : null,
                $pelacakan[$i]['Seri'] ?? [],
            );
        }

        return app(TerimaBarang::class)->Jalankan(new DataPenerimaanBarang($po->Uuid, null, null, $tanggal ?? self::Hari(), 'SJ-'.$po->Id, Uang::Dari($ongkir), null, $baris, null, $idPengguna));
    }

    /**
     * GRN tanpa PO: `baris` = list [Produk, jumlah, harga, diskon?].
     *
     * @param  list<array{0: Produk, 1: string, 2: string, 3?: string}>  $baris
     */
    public static function TerimaTanpaPo(?Pemasok $pemasok, Gudang $gudang, array $baris, int $idPengguna, string $ongkir = '0', ?CarbonImmutable $tanggal = null): PenerimaanBarang
    {
        return app(TerimaBarang::class)->Jalankan(new DataPenerimaanBarang(
            null,
            $pemasok?->Uuid,
            $gudang->Id,
            $tanggal ?? self::Hari(),
            null,
            Uang::Dari($ongkir),
            null,
            array_map(fn (array $b): DataBarisPenerimaanBarang => new DataBarisPenerimaanBarang(null, $b[0]->Uuid, null, Kuantitas::Dari($b[1]), Uang::Dari($b[2]), Uang::Dari($b[3] ?? '0')), $baris),
            null,
            $idPengguna,
        ));
    }

    /**
     * Faktur atas GRN; `harga` = Id baris GRN → harga faktur (selain itu harga GRN).
     *
     * @param  list<PenerimaanBarang>  $grn
     * @param  array<int, string>  $harga
     */
    public static function Fakturkan(Pemasok $pemasok, array $grn, int $idPengguna, array $harga = [], ?string $ongkir = null, ?CarbonImmutable $tanggal = null, ?string $nomor = null): FakturPembelian
    {
        self::$urutan++;

        return app(SimpanFakturPembelian::class)->Jalankan(new DataFakturPembelian(
            $pemasok->Uuid,
            $nomor ?? 'INV/SPN/'.self::$urutan,
            $tanggal ?? self::Hari(),
            null,
            array_map(fn (PenerimaanBarang $g): string => $g->Uuid, $grn),
            array_map(fn (int $id, string $h): DataBarisFakturPembelian => new DataBarisFakturPembelian($id, Uang::Dari($h), Uang::Nol()), array_keys($harga), array_values($harga)),
            $ongkir === null ? null : Uang::Dari($ongkir),
            null,
            null,
            $idPengguna,
        ));
    }

    /**
     * Penjualan tiruan yang lengkap untuk invarian akun: stok keluar lewat `BantuanStokAwal::Jual` (buku stok) lalu
     * jurnal HPP Dr Hpp / Cr persediaan sebesar perubahan nilai persediaan, sehingga saldo akun persediaan tetap =
     * Σ nilai stok.
     */
    public static function Jual(Produk $produk, Gudang $gudang, string $jumlah, ?int $idPengguna = null): void
    {
        self::$urutan++;
        $hasil = BantuanStokAwal::Jual($produk, $gudang, $jumlah);
        $nilai = Uang::Nol()->Kurangi($hasil->TotalHpp());

        if ($nilai->BernilaiNol()) {
            return;
        }

        app(PostingJurnal::class)->Jalankan(new DataJurnal(
            jenisSumber: JenisSumberJurnal::Penjualan,
            idSumber: 900000 + self::$urutan,
            uuidSumber: null,
            nomorSumber: null,
            tanggal: self::Hari(),
            keterangan: 'Penjualan uji (HPP)',
            baris: [DataBarisJurnal::Debit(PeranAkun::Hpp, $nilai, $gudang->IdOutlet), DataBarisJurnal::Kredit(PeranAkun::PersediaanBarangDagang, $nilai, $gudang->IdOutlet)],
            idPengguna: $idPengguna,
        ));
    }

    /** Akun kas/bank bertanda `KasBank` tenant konteks dari kode (template RTL-GEN: 1-1100 kas outlet, 1-1200 bank). */
    public static function AkunKas(string $kode = '1-1100'): Akun
    {
        return Akun::query()->where('Kode', $kode)->firstOrFail();
    }

    /**
     * Saldo akun peran (Σ Debit − Σ Kredit) tenant, SQL mentah lewat pemetaan akun tingkat tenant & outlet.
     */
    public static function SaldoPeran(int $idTenant, PeranAkun $peran): string
    {
        $hasil = DB::selectOne(
            'SELECT CAST(COALESCE(SUM(d.`Debit` - d.`Kredit`), 0) AS DECIMAL(18,2)) AS Saldo FROM `JurnalDetail` d
             WHERE d.`IdTenant` = ? AND d.`IdAkun` IN (SELECT p.`IdAkun` FROM `PemetaanAkun` p WHERE p.`IdTenant` = ? AND p.`Kunci` = ?)',
            [$idTenant, $idTenant, $peran->value],
        );

        return (string) $hasil->Saldo;
    }

    /**
     * Invarian F-04: saldo `HutangUsaha` = −Σ sisa faktur terbuka; saldo `HutangBelumDifakturkan` = −Σ nilai GRN
     * Diposting non-belanja yang belum difakturkan (nilai − diretur); plus semua invarian stok & jurnal F-05a.
     *
     * @return list<string>
     */
    public static function PeriksaInvarian(int $idTenant, bool $fifo = false): array
    {
        $galat = PemeriksaInvarian::PeriksaSemua($idTenant, $fifo);
        $sisaFaktur = (string) DB::selectOne(
            "SELECT CAST(COALESCE(SUM(`Total` - `JumlahDibayar` - `JumlahRetur`), 0) AS DECIMAL(18,2)) AS Sisa FROM `FakturPembelian` WHERE `IdTenant` = ? AND `Status` <> 'Dibatalkan'",
            [$idTenant],
        )->Sisa;
        $hutang = self::SaldoPeran($idTenant, PeranAkun::HutangUsaha);

        if (! BigDecimal::of($hutang)->isEqualTo(BigDecimal::of($sisaFaktur)->negated())) {
            $galat[] = "Saldo HutangUsaha {$hutang} ≠ −Σ sisa faktur {$sisaFaktur}";
        }

        $grni = (string) DB::selectOne(
            "SELECT CAST(COALESCE(SUM(d.`Nilai` - d.`NilaiDiretur`), 0) AS DECIMAL(18,2)) AS Sisa FROM `PenerimaanBarangDetail` d
             JOIN `PenerimaanBarang` g ON g.`Id` = d.`IdPenerimaanBarang`
             WHERE g.`IdTenant` = ? AND g.`Status` = 'Diposting' AND g.`BelanjaStok` = 0 AND g.`IdFakturPembelian` IS NULL",
            [$idTenant],
        )->Sisa;
        $saldoGrni = self::SaldoPeran($idTenant, PeranAkun::HutangBelumDifakturkan);

        if (! BigDecimal::of($saldoGrni)->isEqualTo(BigDecimal::of($grni)->negated())) {
            $galat[] = "Saldo HutangBelumDifakturkan {$saldoGrni} ≠ −Σ GRN belum difakturkan {$grni}";
        }

        return $galat;
    }
}
