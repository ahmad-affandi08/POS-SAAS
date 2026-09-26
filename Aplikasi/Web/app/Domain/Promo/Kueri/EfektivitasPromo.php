<?php

declare(strict_types=1);

namespace App\Domain\Promo\Kueri;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Kueri\ProdukUntukPromo;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Penjualan\Data\DataSaringLaporanPenjualan;
use App\Domain\Penjualan\Enum\JenisKondisiPromo;
use App\Domain\Penjualan\Kueri\AgregatPenjualan;
use App\Domain\Promo\Enum\StatusKlaimPromo;
use App\Domain\Promo\Model\KlaimPromoPemasok;
use App\Domain\Promo\Model\Promo;
use App\Domain\Promo\Model\PromoPemakaian;
use App\Domain\Tenant\Kueri\ProfilTenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * Laporan efektivitas promo (F-16c bagian 4c, "jumlah pakai, nilai diskon, uplift penjualan"):
 * - **Periode promo** = tanggal mulai promo (atau pemakaian pertama bila tanpa tanggal mulai) s.d. tanggal selesai atau
 *   hari ini (yang lebih awal), menurut zona tenant; paling panjang 366 hari terakhir.
 * - **Pembanding** = periode yang sama panjang tepat sebelum periode promo (praktik umum baseline sebelum-sesudah).
 * - **Cakupan** = barang kondisi promo (produk/kategori; semua barang bila kondisi "Semua") di outlet promo (semua outlet
 *   bila tidak dibatasi). Penjualan bersih mengikuti laporan penjualan F-14a (tanpa void, retur mengurangi).
 * - **Uplift** = (bersih periode promo − bersih pembanding) ÷ bersih pembanding; null bila pembanding Rp 0.
 * Angka pakai & potongan hanya dari pemakaian yang tidak dibatalkan void. Bagian pemasok = klaim yang tidak dibatalkan.
 */
final class EfektivitasPromo
{
    private const HARI_MAKSIMAL = 366;

    public function __construct(
        private readonly AgregatPenjualan $agregat,
        private readonly ProdukUntukPromo $produk,
        private readonly PetaUuidOutlet $outlet,
        private readonly KonteksTenant $konteks,
        private readonly ProfilTenant $profil,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function Hitung(Promo $promo): array
    {
        $zona = (string) $this->profil->Ambil($this->konteks->Wajib())['ZonaWaktu'];
        $hariIni = CarbonImmutable::now($zona)->startOfDay();
        $pertama = PromoPemakaian::query()->where('IdPromo', $promo->Id)->whereNull('DibatalkanPada')->min('TanggalBisnis');
        $mulai = $promo->MulaiPada !== null
            ? CarbonImmutable::instance($promo->MulaiPada)->setTimezone($zona)->startOfDay()
            : (is_string($pertama) ? CarbonImmutable::parse($pertama, $zona)->startOfDay() : $hariIni);
        $selesai = $promo->SelesaiPada !== null
            ? CarbonImmutable::instance($promo->SelesaiPada)->setTimezone($zona)->subDay()->startOfDay()
            : $hariIni;
        $selesai = $selesai->greaterThan($hariIni) ? $hariIni : $selesai;

        if ($selesai->lessThan($mulai)) {
            return ['Berjalan' => false, 'Periode' => ['Dari' => $mulai->toDateString(), 'Sampai' => $selesai->toDateString(), 'Hari' => 0]];
        }

        if ($mulai->diffInDays($selesai) + 1 > self::HARI_MAKSIMAL) {
            $mulai = $selesai->subDays(self::HARI_MAKSIMAL - 1);
        }

        $hari = (int) $mulai->diffInDays($selesai) + 1;
        $dariPembanding = $mulai->subDays($hari);
        $sampaiPembanding = $mulai->subDay();

        /** @var array{Outlet?: list<string>, Kondisi?: array{Jenis?: string, Uuid?: list<string>}} $definisi */
        $definisi = $promo->Definisi;
        $uuidOutlet = $definisi['Outlet'] ?? [];
        $idOutlet = $uuidOutlet === [] ? null : array_values($this->outlet->AmbilIdDariUuid($uuidOutlet));
        $jenis = JenisKondisiPromo::tryFrom((string) ($definisi['Kondisi']['Jenis'] ?? 'Semua')) ?? JenisKondisiPromo::Semua;
        $uuidKondisi = $definisi['Kondisi']['Uuid'] ?? [];
        $idProduk = match ($jenis) {
            JenisKondisiPromo::Produk => $this->produk->AmbilDariProduk($uuidKondisi),
            JenisKondisiPromo::Kategori => $this->produk->AmbilDariKategori($uuidKondisi),
            JenisKondisiPromo::Semua => null,
        };

        $sekarang = $this->HitungCakupan($mulai, $selesai, $idOutlet, $idProduk);
        $pembanding = $this->HitungCakupan($dariPembanding, $sampaiPembanding, $idOutlet, $idProduk);
        $pemakaian = PromoPemakaian::query()->where('IdPromo', $promo->Id)->whereNull('DibatalkanPada')
            ->whereBetween('TanggalBisnis', [$mulai->toDateString(), $selesai->toDateString()])
            ->selectRaw('COUNT(*) AS Jumlah, SUM(JumlahDiskon) AS Total')->first();
        $jumlahPakai = (int) ($pemakaian?->getAttribute('Jumlah') ?? 0);
        $potongan = Uang::Dari((string) ($pemakaian?->getAttribute('Total') ?? '0'));
        $klaim = Uang::Dari((string) (KlaimPromoPemasok::query()->where('IdPromo', $promo->Id)
            ->where('Status', '!=', StatusKlaimPromo::Dibatalkan->value)
            ->whereBetween('TanggalBisnis', [$mulai->toDateString(), $selesai->toDateString()])
            ->sum('Jumlah') ?: '0'));
        $bersihPembanding = BigDecimal::of($pembanding['Bersih']);

        return [
            'Berjalan' => true,
            'Periode' => ['Dari' => $mulai->toDateString(), 'Sampai' => $selesai->toDateString(), 'Hari' => $hari],
            'Pembanding' => ['Dari' => $dariPembanding->toDateString(), 'Sampai' => $sampaiPembanding->toDateString()],
            'Cakupan' => $jenis === JenisKondisiPromo::Semua ? 'Semua barang' : ($jenis === JenisKondisiPromo::Produk ? 'Produk promo' : 'Kategori promo'),
            'SemuaOutlet' => $idOutlet === null,
            'JumlahPakai' => $jumlahPakai,
            'TotalPotongan' => $potongan->KeString(),
            'RataPotongan' => $jumlahPakai === 0 ? '0.00' : self::Bagi($potongan, $jumlahPakai),
            'DitanggungPemasok' => $klaim->KeString(),
            'Sekarang' => $sekarang + ['RataHarian' => self::Bagi(Uang::Dari($sekarang['Bersih']), $hari)],
            'Sebelum' => $pembanding + ['RataHarian' => self::Bagi(Uang::Dari($pembanding['Bersih']), $hari)],
            'UpliftPersen' => $bersihPembanding->isZero()
                ? null
                : (string) BigDecimal::of($sekarang['Bersih'])->minus($bersihPembanding)->multipliedBy(100)->dividedBy($bersihPembanding, 1, RoundingMode::HalfUp),
        ];
    }

    /** Rata-rata rupiah (dibulatkan ke sen, tanpa float). */
    private static function Bagi(Uang $nilai, int $pembagi): string
    {
        return (string) BigDecimal::of($nilai->KeString())->dividedBy($pembagi, 2, RoundingMode::HalfUp);
    }

    /**
     * @param  list<int>|null  $idOutlet
     * @param  list<int>|null  $idProduk  null = semua barang
     * @return array{Bersih: string, JumlahTransaksi: int, Qty: string|null}
     */
    private function HitungCakupan(CarbonImmutable $dari, CarbonImmutable $sampai, ?array $idOutlet, ?array $idProduk): array
    {
        $saring = new DataSaringLaporanPenjualan($dari, $sampai, $idOutlet);

        if ($idProduk === null) {
            $total = $this->agregat->Total($saring);

            return ['Bersih' => $total->Bersih()->KeString(), 'JumlahTransaksi' => $total->jumlahTransaksi, 'Qty' => null];
        }

        $cakupan = array_flip($idProduk);
        $bersih = Uang::Nol();
        $qty = Kuantitas::Nol();
        $transaksi = 0;

        foreach ($this->agregat->PerProduk($saring) as $b) {
            if (isset($cakupan[$b['IdProduk']])) {
                $bersih = $bersih->Tambah(Uang::Dari($b['Bersih']));
                $qty = $qty->Tambah(Kuantitas::Dari($b['Qty']));
                $transaksi += $b['JumlahTransaksi'];
            }
        }

        return ['Bersih' => $bersih->KeString(), 'JumlahTransaksi' => $transaksi, 'Qty' => $qty->KeString()];
    }
}
