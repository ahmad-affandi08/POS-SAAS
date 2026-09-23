<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Layanan;

use App\Domain\Katalog\Aksi\PastikanSatuanStandar;
use App\Domain\Katalog\Data\DataSatuanStandar;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Kueri\KatalogPanduan;
use App\Domain\Pajak\Kueri\DaftarKelompokPajak;
use App\Domain\PanduanAwal\Data\DataIsiTemplate;
use App\Domain\Penjualan\Enum\ModeKasir;
use App\Domain\Referensi\Kueri\SatuanStandarAktif;

/**
 * Aturan produk awal panduan (F-01 langkah 4, H14):
 * - Jenis produk tambah cepat: Stok bila mode kasir template memuat Retail/Grosir atau belum ada template; selain itu
 *   NonStok (F&B sampai resep dibuat di F-03).
 * - Kelompok pajak: kelompok pertama template (bila sudah dibuat di tenant).
 * - Satuan dasar: satuan tenant dengan kode standar itu; dibuat dari satuan standar P-02 bila belum ada.
 */
final class PenentuProdukAwal
{
    public const KODE_SATUAN_BAWAAN = 'PCS';

    public function __construct(
        private readonly KatalogPanduan $katalog,
        private readonly DaftarKelompokPajak $kelompokPajak,
        private readonly SatuanStandarAktif $satuanStandar,
        private readonly PastikanSatuanStandar $pastikanSatuan,
    ) {}

    public function TentukanJenisManual(?DataIsiTemplate $isi): JenisProduk
    {
        if ($isi === null) {
            return JenisProduk::Stok;
        }

        $modeStok = [ModeKasir::Retail->value, ModeKasir::Grosir->value];

        return array_intersect($isi->modeKasir, $modeStok) !== [] ? JenisProduk::Stok : JenisProduk::NonStok;
    }

    public function TentukanIdKelompokPajak(DataIsiTemplate $isi): ?int
    {
        $pertama = $isi->kelompokPajak[0] ?? null;

        return $pertama === null ? null : $this->kelompokPajak->CariIdBerdasarkanNama($pertama->nama);
    }

    public function PastikanIdSatuan(string $kodeStandar): int
    {
        $id = $this->katalog->CariIdSatuan($kodeStandar);

        if ($id !== null) {
            return $id;
        }

        $standar = $this->satuanStandar->AmbilBerdasarkanKode([$kodeStandar])[0]
            ?? $this->satuanStandar->AmbilBerdasarkanKode([self::KODE_SATUAN_BAWAAN])[0]
            ?? ['Kode' => self::KODE_SATUAN_BAWAAN, 'Nama' => 'Pcs', 'Simbol' => 'pcs', 'BolehDesimal' => false];

        return $this->katalog->CariIdSatuan($standar['Kode'])
            ?? $this->pastikanSatuan->Jalankan(new DataSatuanStandar($standar['Kode'], $standar['Nama'], $standar['Simbol'], $standar['BolehDesimal']));
    }
}
