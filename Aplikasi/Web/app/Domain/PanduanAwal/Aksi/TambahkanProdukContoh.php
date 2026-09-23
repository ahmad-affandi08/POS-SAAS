<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Aksi\TambahProdukCepat;
use App\Domain\Katalog\Data\DataProdukCepat;
use App\Domain\Katalog\Data\HasilTambahProduk;
use App\Domain\Katalog\Kueri\KatalogPanduan;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\PanduanAwal\Kueri\TemplateTerbit;
use App\Domain\PanduanAwal\Layanan\PembacaIsiTemplate;
use App\Domain\PanduanAwal\Layanan\PenentuProdukAwal;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-01 langkah 4(a): menambahkan produk contoh dari versi template yang diterapkan ke outlet. Harga dari isian
 * pemilik (harga contoh hanya saran). Kategori & satuan dicocokkan ke data tenant hasil template; kelompok pajak =
 * kelompok pertama template. Nama yang sudah ada dilewati (kirim dua kali = idempoten).
 */
final class TambahkanProdukContoh
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly TemplateTerbit $templateTerbit,
        private readonly PembacaIsiTemplate $pembaca,
        private readonly KatalogPanduan $katalog,
        private readonly PenentuProdukAwal $penentu,
        private readonly TambahProdukCepat $tambahProduk,
    ) {}

    /**
     * @param  list<array{Nama: string, Harga: string}>  $pilihan
     */
    public function Jalankan(Outlet $outlet, array $pilihan): HasilTambahProduk
    {
        $versi = $this->templateTerbit->CariVersi($outlet->IdTemplateSektorVersi);
        $isi = $versi === null ? null : $this->pembaca->Baca($versi->Isi);
        $contohPerNama = [];

        foreach ($isi === null ? [] : $isi->produkContoh as $contoh) {
            $contohPerNama[mb_strtolower($contoh->nama)] = $contoh;
        }

        return DB::transaction(function () use ($pilihan, $contohPerNama, $isi): HasilTambahProduk {
            // Urutan kunci: Tenant dulu, baru satuan & produk.
            $this->penguncian->Kunci($this->konteks->Wajib());
            $idKategori = $this->katalog->AmbilIdKategoriPerNama();
            $idKelompokPajak = $isi === null ? null : $this->penentu->TentukanIdKelompokPajak($isi);
            $daftar = [];

            foreach ($pilihan as $satu) {
                $contoh = $contohPerNama[mb_strtolower(trim($satu['Nama']))]
                    ?? throw new PelanggaranAturanBisnis('ProdukContohTidakDikenal', "Produk contoh {$satu['Nama']} tidak ada di template Anda. Muat ulang halaman ini.", 'ProdukContoh');

                $daftar[] = new DataProdukCepat(
                    nama: $contoh->nama,
                    harga: Uang::Dari($satu['Harga']),
                    idKategori: $contoh->kategori === null ? null : ($idKategori[mb_strtolower($contoh->kategori)] ?? null),
                    idSatuanDasar: $this->penentu->PastikanIdSatuan($contoh->kodeSatuan),
                    jenis: $contoh->jenis,
                    idKelompokPajak: $idKelompokPajak,
                );
            }

            return $this->tambahProduk->Jalankan($daftar);
        });
    }
}
