<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Karyawan\Data\DataAturanKomisi;
use App\Domain\Karyawan\Enum\CakupanKomisi;
use App\Domain\Karyawan\Enum\JenisKomisi;
use App\Domain\Karyawan\Model\AturanKomisi;
use App\Domain\Katalog\Kueri\NamaProduk;
use App\Domain\Katalog\Kueri\PohonKategori;

/**
 * Tambah/ubah aturan komisi (F-18, EMP-04, izin `karyawan.kelola`). Cakupan Produk/Kategori wajib menunjuk produk/
 * kategori tenant; persen 0–100; nominal > 0. Perubahan hanya berlaku untuk penjualan berikutnya (komisi yang sudah
 * tercatat tidak dihitung ulang). Audit `aturan-komisi.tambah|ubah`.
 */
final class SimpanAturanKomisi
{
    public function __construct(
        private readonly NamaProduk $namaProduk,
        private readonly PohonKategori $kategori,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(DataAturanKomisi $data, ?AturanKomisi $aturan = null): AturanKomisi
    {
        $nama = trim($data->nama);

        if ($nama === '' || mb_strlen($nama) > 100) {
            throw new PelanggaranAturanBisnis('NamaTidakValid', 'Isi nama aturan, paling panjang 100 karakter.', 'Nama');
        }

        $uuidProduk = $data->cakupan === CakupanKomisi::Produk ? $data->uuidProduk : null;
        $uuidKategori = $data->cakupan === CakupanKomisi::Kategori ? $data->uuidKategori : null;

        if ($data->cakupan === CakupanKomisi::Produk && ($uuidProduk === null || $this->namaProduk->Ambil([$uuidProduk]) === [])) {
            throw new PelanggaranAturanBisnis('ProdukTidakDitemukan', 'Pilih produk untuk aturan ini.', 'UuidProduk');
        }

        if ($data->cakupan === CakupanKomisi::Kategori && ! in_array($uuidKategori, array_column($this->kategori->AmbilOpsi(), 'Uuid'), true)) {
            throw new PelanggaranAturanBisnis('KategoriTidakDitemukan', 'Pilih kategori untuk aturan ini.', 'UuidKategori');
        }

        if ($data->nilai->Bandingkan(Uang::Nol()) <= 0 || ($data->jenis === JenisKomisi::Persen && $data->nilai->Bandingkan(Uang::Dari('100')) > 0)) {
            throw new PelanggaranAturanBisnis('NilaiTidakValid', $data->jenis === JenisKomisi::Persen ? 'Persen komisi harus lebih dari 0 dan paling banyak 100.' : 'Nominal komisi harus lebih dari Rp 0.', 'Nilai');
        }

        $level = $data->levelStaf === null ? '' : trim($data->levelStaf);
        $isian = [
            'Nama' => $nama,
            'Cakupan' => $data->cakupan,
            'UuidProduk' => $uuidProduk,
            'UuidKategori' => $uuidKategori,
            'LevelStaf' => $level === '' ? null : mb_substr($level, 0, 40),
            'Jenis' => $data->jenis,
            'Nilai' => $data->nilai->KeString(),
        ];
        $audit = [...$isian, 'Cakupan' => $data->cakupan->value, 'Jenis' => $data->jenis->value];

        if ($aturan === null) {
            $baru = AturanKomisi::query()->create($isian);
            $this->audit->Catat('aturan-komisi.tambah', $baru, nilaiBaru: $audit, idPengguna: $data->idPengguna);

            return $baru;
        }

        $lama = [...$aturan->only(array_keys($isian)), 'Cakupan' => $aturan->Cakupan->value, 'Jenis' => $aturan->Jenis->value];
        $aturan->fill($isian)->save();
        $this->audit->Catat('aturan-komisi.ubah', $aturan, $lama, $audit, idPengguna: $data->idPengguna);

        return $aturan;
    }
}
