<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Katalog\Data\DataProduk;
use App\Domain\Katalog\Model\PaketSesi;
use App\Domain\Katalog\Model\Produk;
use Illuminate\Support\Facades\DB;

/**
 * D-23 B: buat produk Jasa sekaligus definisi paket sesinya dari satu formulir produk ("Jual sebagai paket sesi").
 * Keduanya satu transaksi: gagal salah satu = tidak ada yang tersimpan. Semua layanan Jasa bisa ditukar dengan sesinya
 * (daftar layanan tertentu tetap diatur di halaman Paket sesi). Kirim ulang dengan `Uuid` produk yang sama idempoten.
 */
final class SimpanProdukDenganPaketSesi
{
    public function __construct(
        private readonly SimpanProduk $simpanProduk,
        private readonly SimpanPaketSesi $simpanPaket,
    ) {}

    public function Jalankan(DataProduk $data, int $jumlahSesi, ?int $masaBerlakuHari, int $idPengguna): Produk
    {
        return DB::transaction(function () use ($data, $jumlahSesi, $masaBerlakuHari, $idPengguna): Produk {
            $produk = $this->simpanProduk->Jalankan(null, $data);

            if (! PaketSesi::query()->where('IdProduk', $produk->Id)->exists()) {
                $this->simpanPaket->Jalankan(null, $produk->Uuid, $jumlahSesi, $masaBerlakuHari, true, [], true, $idPengguna);
            }

            return $produk;
        });
    }
}
