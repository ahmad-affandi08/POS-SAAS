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
use App\Domain\Penjualan\Data\HasilHitungGrosir;
use App\Domain\Penjualan\Layanan\PenghitungGrosir;
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
 * Bagian 1 sengaja menolak sparepart berpelacakan batch/nomor seri: alokasi batch/seri terjadi di penjualan kasir, dan
 * estimasi yang tidak tahu unit mana yang dipasang hanya akan menunda galatnya ke kasir.
 *
 * @phpstan-type BarisSiap array{Jenis: JenisBarisPerintahKerja, IdProduk: int, NamaProduk: string, Sku: string|null, IdProdukSatuan: int, SimbolSatuan: string, Jumlah: Kuantitas, HargaSatuan: Uang, Diskon: Uang, IdKelompokPajak: int|null, HargaTermasukPajak: bool|null, IdKaryawan: int|null, Catatan: string|null}
 */
final class PenyusunBarisPerintahKerja
{
    public function __construct(
        private readonly HargaProdukBerlaku $harga,
        private readonly JadwalStafReservasi $staf,
        private readonly PenghitungGrosir $penghitung,
    ) {}

    /**
     * @param  list<DataBarisPerintahKerja>  $baris
     * @return list<BarisSiap>
     */
    public function Susun(array $baris, int $idOutlet, ?string $kodeTier): array
    {
        $hasil = [];

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

        if ($jenis === JenisBarisPerintahKerja::Sparepart && $produk->Pelacakan !== PelacakanProduk::Tidak) {
            throw new PelanggaranAturanBisnis(
                'PelacakanBelumDidukung',
                "Perintah kerja belum mendukung sparepart bernomor batch atau seri ({$produk->Nama}). Tambahkan sparepart itu langsung di kasir saat menagih.",
                $bidang,
            );
        }
    }
}
