<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Data\DataInfoProdukStok;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Katalog\Kueri\SatuanProdukPembelian;
use App\Domain\Pembelian\Data\DataBarisTerhitung;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Memeriksa & menghitung baris pembelian (PO, penerimaan tanpa PO, belanja stok; F-04 fase 1): produk berstok milik
 * tenant (bukan konsinyasi, tidak diarsipkan/dihapus), satuan pembelian produk itu (null = satuan dasar), jumlah > 0,
 * jumlah dasar = jumlah × konversi (bulat untuk satuan dasar tanpa desimal), harga ≥ 0, diskon 0..bruto. Galat per
 * baris memakai bidang `Baris.{i}.…`.
 */
final class PenyelesaiBarisPembelian
{
    public function __construct(
        private readonly InfoProdukStok $infoProduk,
        private readonly SatuanProdukPembelian $satuan,
    ) {}

    /**
     * @param  list<string>  $uuidProduk
     * @return array<string, DataInfoProdukStok> kunci = Uuid
     */
    public function AmbilProduk(array $uuidProduk): array
    {
        return $this->infoProduk->AmbilDariUuid($uuidProduk);
    }

    /**
     * @param  array<string, DataInfoProdukStok>  $produk  kunci = Uuid
     *
     * @throws PelanggaranAturanBisnis ProdukTidakDikenal, ProdukTanpaStok, SatuanTidakDikenal, JumlahTidakValid, HargaTidakValid
     */
    public function Hitung(int $indeks, array $produk, string $uuidProduk, ?string $uuidProdukSatuan, Kuantitas $jumlah, Uang $harga, Uang $diskon): DataBarisTerhitung
    {
        $info = $produk[$uuidProduk] ?? null;

        if ($info === null || $info->dihapus) {
            throw self::Galat($indeks, 'Produk', 'ProdukTidakDikenal', 'Produk tidak ditemukan.');
        }

        if (! $info->jenis->CekPunyaStok() || $info->jenis === JenisProduk::Konsinyasi) {
            throw self::Galat($indeks, 'Produk', 'ProdukTanpaStok', "{$info->nama} tidak bisa dibeli sebagai stok (jenis {$info->jenis->AmbilLabel()}).");
        }

        if ($info->diarsipkan) {
            throw self::Galat($indeks, 'Produk', 'ProdukDiarsipkan', "{$info->nama} sudah diarsipkan. Aktifkan lagi produknya bila masih dibeli.");
        }

        [$idProdukSatuan, $simbol, $konversi] = [null, $info->simbolSatuan, Kuantitas::Dari(1)];

        if ($uuidProdukSatuan !== null && $uuidProdukSatuan !== '') {
            $satuan = $this->satuan->CariDariUuid($uuidProdukSatuan, $info->id);

            if ($satuan === null) {
                throw self::Galat($indeks, 'UuidProdukSatuan', 'SatuanTidakDikenal', "Satuan pembelian {$info->nama} tidak dikenal.");
            }

            [$idProdukSatuan, $simbol, $konversi] = [$satuan['Id'], $satuan['Simbol'], Kuantitas::Dari($satuan['Konversi'])];
        }

        return self::HitungNilai($indeks, $info, $idProdukSatuan, $simbol, $konversi, $jumlah, $harga, $diskon);
    }

    /**
     * Perhitungan baris dengan satuan yang sudah diketahui (baris PO yang diterima memakai snapshot satuan PO).
     *
     * @throws PelanggaranAturanBisnis JumlahTidakValid, HargaTidakValid
     */
    public static function HitungNilai(int $indeks, DataInfoProdukStok $info, ?int $idProdukSatuan, string $simbol, Kuantitas $konversi, Kuantitas $jumlah, Uang $harga, Uang $diskon): DataBarisTerhitung
    {
        if (! $jumlah->KeDesimal()->isPositive()) {
            throw self::Galat($indeks, 'Jumlah', 'JumlahTidakValid', "Jumlah {$info->nama} harus lebih dari 0.");
        }

        $dasar = $jumlah->KeDesimal()->multipliedBy($konversi->KeDesimal());

        if ($dasar->getScale() > Kuantitas::SKALA && ! $dasar->toScale(Kuantitas::SKALA, RoundingMode::Down)->isEqualTo($dasar)) {
            throw self::Galat($indeks, 'Jumlah', 'JumlahTidakValid', "Jumlah {$info->nama} dalam satuan dasar maksimal 4 desimal.");
        }

        $jumlahDasar = Kuantitas::Dari($dasar->toScale(Kuantitas::SKALA, RoundingMode::Down));

        if (! $info->bolehDesimal && ! $jumlahDasar->KeDesimal()->getFractionalPart()->isZero()) {
            throw self::Galat($indeks, 'Jumlah', 'JumlahTidakValid', "Jumlah {$info->nama} harus bilangan bulat {$info->simbolSatuan}.");
        }

        if ($harga->BernilaiNegatif()) {
            throw self::Galat($indeks, 'Harga', 'HargaTidakValid', "Harga {$info->nama} tidak boleh negatif.");
        }

        $bruto = Uang::Dari(BigDecimal::of($harga->KeString())->multipliedBy($jumlah->KeDesimal())->toScale(Uang::SKALA, RoundingMode::HalfUp));

        if ($diskon->BernilaiNegatif() || $diskon->Bandingkan($bruto) > 0) {
            throw self::Galat($indeks, 'Diskon', 'HargaTidakValid', "Diskon {$info->nama} harus antara Rp 0 dan {$bruto->FormatRupiah()}.");
        }

        return new DataBarisTerhitung($info, $idProdukSatuan, $simbol, $konversi, $jumlah, $jumlahDasar, $harga, $diskon, $bruto, $bruto->Kurangi($diskon));
    }

    public static function Galat(int $indeks, string $bidang, string $kode, string $pesan): PelanggaranAturanBisnis
    {
        return new PelanggaranAturanBisnis($kode, 'Baris '.($indeks + 1).": {$pesan}", "Baris.{$indeks}.{$bidang}", detail: ['Baris' => $indeks + 1]);
    }
}
