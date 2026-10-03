<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Harga\Aksi\SimpanHargaProduk;
use App\Domain\Katalog\Harga\Data\DataBarisHarga;
use App\Domain\Katalog\Harga\Enum\SumberPerubahanHarga;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukHarga;
use App\Domain\Katalog\Model\ProdukSatuan;
use Illuminate\Support\Facades\DB;

/**
 * Audit kemudahan pakai #18 (F-03): ubah harga dasar & tambah barcode banyak varian sekaligus dari tabel varian di
 * halaman produk induk. Per varian, harga yang diubah hanya baris harga dasar jumlah minimum 1 pada satuan jual
 * bawaan (harga bertingkat lain dipertahankan) lewat `SimpanHargaProduk` (riwayat & audit harga sama); barcode
 * ditambahkan lewat `TambahBarcodeProduk` (barcode milik produk lain ditolak BR-03.1). Semua atau tidak sama sekali.
 */
final class SimpanVarianMassal
{
    public function __construct(
        private readonly SimpanHargaProduk $simpanHarga,
        private readonly TambahBarcodeProduk $tambahBarcode,
    ) {}

    /**
     * @param  list<array{Uuid: string, Harga: ?Uang, Barcode: ?string}>  $baris
     *
     * @throws PelanggaranAturanBisnis BukanIndukVarian, VarianTidakDikenal, IzinHargaDiperlukan, aturan harga/barcode
     */
    public function Jalankan(Produk $induk, array $baris, bool $bolehUbahHarga): int
    {
        if ($induk->Jenis !== JenisProduk::IndukVarian) {
            throw new PelanggaranAturanBisnis('BukanIndukVarian', 'Produk ini tidak punya varian.', 'Baris');
        }

        return DB::transaction(function () use ($induk, $baris, $bolehUbahHarga): int {
            $anak = Produk::query()->where('IdInduk', $induk->Id)->get()->keyBy('Uuid');
            $diubah = 0;

            foreach ($baris as $i => $b) {
                $varian = $anak->get($b['Uuid']);

                if (! $varian instanceof Produk) {
                    throw new PelanggaranAturanBisnis('VarianTidakDikenal', 'Varian tidak ditemukan. Muat ulang halaman.', "Baris.{$i}.Uuid");
                }

                $satuan = ProdukSatuan::query()->where('IdProduk', $varian->Id)
                    ->orderByDesc('DefaultJual')->orderBy('Id')->first();

                if (! $satuan instanceof ProdukSatuan) {
                    continue;
                }

                if ($b['Harga'] !== null) {
                    if (! $bolehUbahHarga) {
                        throw new PelanggaranAturanBisnis('IzinHargaDiperlukan', 'Anda tidak punya izin mengubah harga jual.', "Baris.{$i}.Harga");
                    }

                    $lama = ProdukHarga::query()->where('IdProdukSatuan', $satuan->Id)->whereNull('IdDaftarHarga')
                        ->orderBy('JumlahMinimum')->get();
                    $daftar = [new DataBarisHarga(Kuantitas::Dari('1'), $b['Harga'])];

                    foreach ($lama as $h) {
                        if (! Kuantitas::Dari($h->JumlahMinimum)->SamaDengan(Kuantitas::Dari('1'))) {
                            $daftar[] = new DataBarisHarga(Kuantitas::Dari($h->JumlahMinimum), Uang::Dari($h->Harga));
                        }
                    }

                    if ($this->simpanHarga->Jalankan($varian, [$satuan->Id => $daftar], SumberPerubahanHarga::Manual)->CekAdaPerubahan()) {
                        $diubah++;
                    }
                }

                $barcode = trim((string) $b['Barcode']);

                if ($barcode !== '') {
                    $this->tambahBarcode->Jalankan($satuan, $barcode);
                }
            }

            return $diubah;
        });
    }
}
