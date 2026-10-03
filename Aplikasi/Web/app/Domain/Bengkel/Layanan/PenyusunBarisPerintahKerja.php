<?php

declare(strict_types=1);

namespace App\Domain\Bengkel\Layanan;

use App\Domain\Bengkel\Data\DataBarisPerintahKerja;
use App\Domain\Bengkel\Enum\JenisBarisPerintahKerja;
use App\Domain\Bengkel\Model\PerintahKerjaDetail;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Karyawan\Kueri\JadwalStafReservasi;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Enum\PelacakanProduk;
use App\Domain\Katalog\Harga\Kueri\HargaProdukBerlaku;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Organisasi\Kueri\OutletPenjualan;
use App\Domain\Penjualan\Data\HasilHitungGrosir;
use App\Domain\Penjualan\Layanan\PenghitungGrosir;
use App\Domain\Persediaan\Kueri\InfoNomorSeri;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Baris perintah kerja (§9.10): validasi produk per jenis, harga dari **price engine server** (`HargaProdukBerlaku`,
 * tier pelanggan — harga dari klien tidak pernah dipakai), dan angka estimasi lewat mesin kalkulasi F-07a yang sama
 * dengan kasir (`PenghitungGrosir`: pajak per kelompok pajak produk, inklusif/eksklusif, pembulatan per dokumen).
 *
 * Angka estimasi adalah **perkiraan**: saat ditagih, kasir masih bisa menerapkan promo, pembulatan tunai, atau biaya
 * layanan, dan penjualan itulah dokumen keuangannya.
 *
 * Sparepart berpelacakan (bagian 3): **batch** tidak dipilih di sini — server mengalokasikannya FEFO saat penjualan
 * tagihannya diterima (F-05g). **Nomor seri** boleh dicatat per unit (`NomorSeri`, jumlah satuan dasar harus bulat);
 * bila diisi, jumlahnya harus sama dengan unit dan setiap nomor harus `Tersedia` di gudang toko outlet saat disimpan.
 * Nomor tidak dipesan (perintah kerja tidak menggerakkan stok): penjualan tagihan memvalidasinya lagi, dan nomor yang
 * keburu terjual menjadi tinjauan `SerialBermasalah` seperti penjualan kasir biasa. Kosong = diisi kasir saat menagih.
 *
 * @phpstan-type BarisSiap array{Jenis: JenisBarisPerintahKerja, IdProduk: int, NamaProduk: string, Sku: string|null, IdProdukSatuan: int, SimbolSatuan: string, Jumlah: Kuantitas, HargaSatuan: Uang, Diskon: Uang, IdKelompokPajak: int|null, HargaTermasukPajak: bool|null, IdKaryawan: int|null, Catatan: string|null, NomorSeri: list<string>|null}
 */
final class PenyusunBarisPerintahKerja
{
    public function __construct(
        private readonly HargaProdukBerlaku $harga,
        private readonly JadwalStafReservasi $staf,
        private readonly PenghitungGrosir $penghitung,
        private readonly InfoNomorSeri $seri,
        private readonly OutletPenjualan $outlet,
    ) {}

    /**
     * @param  list<DataBarisPerintahKerja>  $baris
     * @return list<BarisSiap>
     */
    public function Susun(array $baris, int $idOutlet, ?string $kodeTier): array
    {
        $hasil = [];
        /** @var array<string, true> $seriDipakai nomor seri per produk yang sudah dipakai baris sebelumnya */
        $seriDipakai = [];

        foreach ($baris as $indeks => $b) {
            $bidang = "Baris.{$indeks}";
            $produk = Produk::query()->where('Uuid', $b->uuidProduk)->whereNull('DiarsipkanPada')->first()
                ?? throw new PelanggaranAturanBisnis('ProdukTidakDikenal', 'Produk tidak ditemukan atau sudah diarsipkan.', "{$bidang}.UuidProduk");
            $satuan = $this->CariSatuan($produk, $b->uuidProdukSatuan)
                ?? throw new PelanggaranAturanBisnis('SatuanTidakDikenal', "Satuan {$produk->Nama} tidak ditemukan.", "{$bidang}.UuidProdukSatuan");
            $this->PastikanJenisCocok($produk, $b->jenis, "{$bidang}.UuidProduk");

            if (! $b->jumlah->KeDesimal()->isPositive()) {
                throw new PelanggaranAturanBisnis('JumlahTidakValid', "Jumlah {$produk->Nama} harus lebih dari nol.", "{$bidang}.Jumlah");
            }

            $nomorSeri = $this->PeriksaNomorSeri($produk, $satuan, $b, $idOutlet, $bidang);

            // Unit yang sama tidak boleh muncul di dua baris (penagihan di kasir akan menolak seluruh penjualan).
            foreach ($nomorSeri ?? [] as $n) {
                $kunciSeri = $produk->Id.'|'.mb_strtoupper($n);

                if (isset($seriDipakai[$kunciSeri])) {
                    throw new PelanggaranAturanBisnis('NomorSeriGanda', "Nomor seri {$n} {$produk->Nama} sudah dipakai di baris lain.", "{$bidang}.NomorSeri");
                }

                $seriDipakai[$kunciSeri] = true;
            }
            $harga = $this->harga->Tentukan($produk, $satuan, $b->jumlah, $idOutlet, null, $kodeTier, CarbonImmutable::now())
                ?? throw new PelanggaranAturanBisnis('HargaBelumDiatur', "{$produk->Nama} belum punya harga jual untuk satuan itu. Atur harganya dulu di katalog.", "{$bidang}.UuidProduk");

            if ($b->diskon->BernilaiNegatif() || $b->diskon->Bandingkan($harga->harga->Kali($b->jumlah->KeString())) > 0) {
                throw new PelanggaranAturanBisnis('DiskonMelebihiBaris', "Diskon {$produk->Nama} tidak boleh negatif atau melebihi nilai barisnya.", "{$bidang}.Diskon");
            }

            $idKaryawan = null;

            if ($b->uuidKaryawan !== null) {
                if ($b->jenis !== JenisBarisPerintahKerja::Jasa) {
                    throw new PelanggaranAturanBisnis('MekanikHanyaJasa', 'Mekanik hanya ditugaskan pada baris jasa.', "{$bidang}.UuidKaryawan");
                }

                $idKaryawan = ($this->staf->CariStaf($b->uuidKaryawan) ?? throw new PelanggaranAturanBisnis('MekanikTidakDikenal', 'Mekanik tidak ditemukan atau sudah tidak aktif.', "{$bidang}.UuidKaryawan"))['Id'];
            }

            $catatan = $b->catatan === null ? null : trim($b->catatan);

            $hasil[] = [
                'Jenis' => $b->jenis,
                'IdProduk' => $produk->Id,
                'NamaProduk' => mb_substr($produk->Nama, 0, 150),
                'Sku' => $produk->Sku,
                'IdProdukSatuan' => $satuan->Id,
                'SimbolSatuan' => (string) $satuan->SatuanUnit->Simbol,
                'Jumlah' => $b->jumlah,
                'HargaSatuan' => $harga->harga,
                'Diskon' => $b->diskon,
                'IdKelompokPajak' => $produk->IdKelompokPajak,
                'HargaTermasukPajak' => $produk->HargaTermasukPajak,
                'IdKaryawan' => $idKaryawan,
                'Catatan' => $catatan === '' || $catatan === null ? null : mb_substr($catatan, 0, 255),
                'NomorSeri' => $nomorSeri,
            ];
        }

        return $hasil;
    }

    /**
     * Angka estimasi baris yang sudah tersusun (atau tersimpan) dengan mesin kalkulasi kasir.
     *
     * @param  list<array{Jumlah: Kuantitas, HargaSatuan: Uang, Diskon: Uang, IdKelompokPajak: int|null, HargaTermasukPajak: bool|null}>  $baris
     */
    public function Hitung(int $idOutlet, ?string $kodeKota, array $baris): HasilHitungGrosir
    {
        return $this->penghitung->Hitung($idOutlet, $kodeKota, array_values(array_map(fn (array $b): array => [
            'Jumlah' => $b['Jumlah'],
            'HargaSatuan' => $b['HargaSatuan'],
            'Diskon' => $b['Diskon'],
            'IdKelompokPajak' => $b['IdKelompokPajak'],
            'HargaTermasukPajak' => $b['HargaTermasukPajak'],
        ], $baris)));
    }

    /**
     * Baris tersimpan dalam bentuk masukan `Hitung()`.
     *
     * @param  Collection<int, PerintahKerjaDetail>|list<PerintahKerjaDetail>  $detail
     * @return list<array{Jumlah: Kuantitas, HargaSatuan: Uang, Diskon: Uang, IdKelompokPajak: int|null, HargaTermasukPajak: bool|null}>
     */
    public static function DariDetail(Collection|array $detail): array
    {
        $hasil = [];

        foreach ($detail as $d) {
            $hasil[] = [
                'Jumlah' => Kuantitas::Dari((string) $d->Jumlah),
                'HargaSatuan' => Uang::Dari((string) $d->HargaSatuan),
                'Diskon' => Uang::Dari((string) $d->Diskon),
                'IdKelompokPajak' => $d->IdKelompokPajak,
                'HargaTermasukPajak' => $d->HargaTermasukPajak,
            ];
        }

        return $hasil;
    }

    private function CariSatuan(Produk $produk, ?string $uuidSatuan): ?ProdukSatuan
    {
        $kueri = ProdukSatuan::query()->with('SatuanUnit')->where('IdProduk', $produk->Id);

        return $uuidSatuan === null
            ? $kueri->where('IdSatuan', $produk->IdSatuanDasar)->first()
            : $kueri->where('Uuid', $uuidSatuan)->first();
    }

    private function PastikanJenisCocok(Produk $produk, JenisBarisPerintahKerja $jenis, string $bidang): void
    {
        if (! $produk->Aktif) {
            throw new PelanggaranAturanBisnis('ProdukTidakAktif', "{$produk->Nama} sudah tidak aktif.", $bidang);
        }

        if ($jenis === JenisBarisPerintahKerja::Jasa && $produk->Jenis !== JenisProduk::Jasa) {
            throw new PelanggaranAturanBisnis('BukanProdukJasa', "Baris jasa harus produk berjenis Jasa; {$produk->Nama} berjenis {$produk->Jenis->value}.", $bidang);
        }

        if ($jenis === JenisBarisPerintahKerja::Sparepart && $produk->Jenis !== JenisProduk::Stok) {
            throw new PelanggaranAturanBisnis('BukanProdukStok', "Sparepart harus produk berstok biasa; {$produk->Nama} berjenis {$produk->Jenis->value}.", $bidang);
        }

    }

    /**
     * Nomor seri baris (null bila produk tidak bernomor seri atau belum diisi).
     *
     * @return list<string>|null
     */
    private function PeriksaNomorSeri(Produk $produk, ProdukSatuan $satuan, DataBarisPerintahKerja $b, int $idOutlet, string $bidang): ?array
    {
        if ($produk->Pelacakan !== PelacakanProduk::Seri) {
            if ($b->nomorSeri !== []) {
                throw new PelanggaranAturanBisnis('NomorSeriBukanUntukProduk', "{$produk->Nama} tidak memakai nomor seri.", "{$bidang}.NomorSeri");
            }

            return null;
        }

        $unit = $b->jumlah->KeDesimal()->multipliedBy((string) $satuan->KonversiKeDasar);

        if (! $unit->getFractionalPart()->isZero()) {
            throw new PelanggaranAturanBisnis('JumlahSeriHarusBulat', "{$produk->Nama} memakai nomor seri: jumlahnya harus unit bulat.", "{$bidang}.Jumlah");
        }

        if ($b->nomorSeri === []) {
            return null;
        }

        $nomor = array_values(array_map(fn (string $n): string => mb_substr(trim($n), 0, 100), $b->nomorSeri));
        $kunci = array_map('mb_strtoupper', $nomor);

        if (count(array_unique($kunci)) !== count($kunci)) {
            throw new PelanggaranAturanBisnis('NomorSeriGanda', "Nomor seri {$produk->Nama} tidak boleh ganda.", "{$bidang}.NomorSeri");
        }

        $jumlahUnit = $unit->toBigInteger()->toInt();

        if (count($nomor) !== $jumlahUnit) {
            throw new PelanggaranAturanBisnis('JumlahNomorSeriTidakCocok', "Isi {$jumlahUnit} nomor seri {$produk->Nama} (satu per unit), baru ".count($nomor).'.', "{$bidang}.NomorSeri");
        }

        $idGudang = $this->outlet->AmbilIdGudangToko($idOutlet);
        $tersedia = $idGudang === null ? [] : $this->seri->CariTersedia($produk->Id, $idGudang, $nomor);
        $hilang = array_values(array_filter($nomor, fn (string $n): bool => ! isset($tersedia[mb_strtoupper($n)])));

        if ($hilang !== []) {
            throw new PelanggaranAturanBisnis('NomorSeriTidakTersedia', 'Nomor seri '.implode(', ', $hilang)." {$produk->Nama} tidak tersedia di stok toko outlet ini.", "{$bidang}.NomorSeri");
        }

        return $nomor;
    }
}
