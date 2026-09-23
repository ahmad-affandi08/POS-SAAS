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
 * F-01 langkah 4(d): tambah produk cepat (nama, harga, kategori opsional). Satuan dasar PCS; jenis & kelompok pajak
 * mengikuti template outlet (H14). Kategori dicari lewat Uuid di tenant aktif; kategori tenant lain = tidak ada.
 */
final class TambahkanProdukManual
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
     * @param  list<array{Nama: string, Harga: string, Kategori: string|null}>  $produk
     */
    public function Jalankan(Outlet $outlet, array $produk): HasilTambahProduk
    {
        $versi = $this->templateTerbit->CariVersi($outlet->IdTemplateSektorVersi);
        $isi = $versi === null ? null : $this->pembaca->Baca($versi->Isi);
        $jenis = $this->penentu->TentukanJenisManual($isi);

        return DB::transaction(function () use ($produk, $isi, $jenis): HasilTambahProduk {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $idKelompokPajak = $isi === null ? null : $this->penentu->TentukanIdKelompokPajak($isi);
            $daftar = [];

            foreach ($produk as $indeks => $satu) {
                $idKategori = null;

                if ($satu['Kategori'] !== null) {
                    $idKategori = $this->katalog->CariIdKategoriUuid($satu['Kategori'])
                        ?? throw new PelanggaranAturanBisnis('KategoriTidakDitemukan', 'Pilih kategori dari daftar.', "Produk.{$indeks}.Kategori");
                }

                $daftar[] = new DataProdukCepat(
                    nama: $satu['Nama'],
                    harga: Uang::Dari($satu['Harga']),
                    idKategori: $idKategori,
                    idSatuanDasar: $this->penentu->PastikanIdSatuan(PenentuProdukAwal::KODE_SATUAN_BAWAAN),
                    jenis: $jenis,
                    idKelompokPajak: $idKelompokPajak,
                );
            }

            return $this->tambahProduk->Jalankan($daftar);
        });
    }
}
