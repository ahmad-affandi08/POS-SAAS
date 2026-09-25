<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Layanan;

use App\Domain\Akuntansi\Data\DataAkunTemplate;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Enum\SaldoNormal;
use App\Domain\Akuntansi\Enum\TipeAkun;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Pajak\Data\DataKelompokPajakTemplate;
use App\Domain\PanduanAwal\Data\DataIsiTemplate;
use App\Domain\PanduanAwal\Data\DataPengaturanTemplate;
use App\Domain\PanduanAwal\Data\DataProdukContohTemplate;

/**
 * Membaca `TemplateSektorVersi.Isi` menjadi DTO (F-01). Versi terbit sudah lolos validasi P-03, tetapi pembacaan
 * tetap defensif: nilai yang rusak diabaikan (menjadi daftar kosong/null), tidak memicu exception. Validasi aturan
 * isi tetap tugas `ValidatorTemplate` (P-03), bukan kelas ini.
 */
final class PembacaIsiTemplate
{
    public const POLA_HARGA = '/^\d{1,16}(\.\d{1,2})?$/';

    /**
     * @param  array<mixed>  $isi
     */
    public function Baca(array $isi): DataIsiTemplate
    {
        return new DataIsiTemplate(
            akun: self::BacaAkun($isi['Akun'] ?? null),
            pemetaanAkun: self::BacaPemetaan($isi['PemetaanAkun'] ?? null),
            kategori: self::BacaDaftarTeks($isi['Kategori'] ?? null),
            kodeSatuan: self::BacaDaftarTeks($isi['KodeSatuan'] ?? null),
            kelompokPajak: self::BacaKelompokPajak($isi['KelompokPajak'] ?? null),
            kunciFitur: self::BacaDaftarTeks($isi['KunciFitur'] ?? null),
            modeKasir: self::BacaDaftarTeks($isi['ModeKasir'] ?? null),
            modeKasirDefault: is_string($isi['ModeKasirDefault'] ?? null) ? $isi['ModeKasirDefault'] : null,
            pengaturan: self::BacaPengaturan($isi['Pengaturan'] ?? null),
            produkContoh: self::BacaProdukContoh($isi['ProdukContoh'] ?? null),
            stasiunDapur: self::BacaDaftarTeks($isi['StasiunDapur'] ?? null),
        );
    }

    /**
     * @return list<string>
     */
    private static function BacaDaftarTeks(mixed $nilai): array
    {
        if (! is_array($nilai)) {
            return [];
        }

        $hasil = [];

        foreach ($nilai as $satu) {
            if (is_string($satu) && trim($satu) !== '') {
                $hasil[] = trim($satu);
            }
        }

        return array_values(array_unique($hasil));
    }

    /**
     * @return list<DataAkunTemplate>
     */
    private static function BacaAkun(mixed $nilai): array
    {
        $hasil = [];

        foreach (is_array($nilai) ? $nilai : [] as $akun) {
            if (! is_array($akun) || ! is_string($akun['Kode'] ?? null) || ! is_string($akun['Nama'] ?? null) || trim($akun['Nama']) === '') {
                continue;
            }

            $tipe = is_string($akun['Tipe'] ?? null) ? TipeAkun::tryFrom($akun['Tipe']) : null;

            if ($tipe === null) {
                continue;
            }

            $saldoNormal = (is_string($akun['SaldoNormal'] ?? null) ? SaldoNormal::tryFrom($akun['SaldoNormal']) : null)
                ?? $tipe->AmbilSaldoNormal(($akun['Kontra'] ?? false) === true);

            $hasil[] = new DataAkunTemplate(trim($akun['Kode']), trim($akun['Nama']), $tipe, $saldoNormal);
        }

        return $hasil;
    }

    /**
     * @return array<string, string>
     */
    private static function BacaPemetaan(mixed $nilai): array
    {
        $hasil = [];

        // Kunci lama diganti kunci barunya; bila keduanya ada, kunci baru menang (§25 no. 16a).
        foreach (is_array($nilai) ? PeranAkun::NormalisasiPemetaan($nilai) : [] as $kunci => $kode) {
            if (is_string($kunci) && is_string($kode) && $kode !== '') {
                $hasil[$kunci] = $kode;
            }
        }

        return $hasil;
    }

    /**
     * @return list<DataKelompokPajakTemplate>
     */
    private static function BacaKelompokPajak(mixed $nilai): array
    {
        $hasil = [];

        foreach (is_array($nilai) ? $nilai : [] as $kelompok) {
            if (! is_array($kelompok) || ! is_string($kelompok['Nama'] ?? null) || trim($kelompok['Nama']) === '') {
                continue;
            }

            $detail = [];

            foreach (is_array($kelompok['Detail'] ?? null) ? $kelompok['Detail'] : [] as $baris) {
                if (is_array($baris) && is_string($baris['KodeJenisPajak'] ?? null) && is_string($baris['DasarPengenaan'] ?? null) && is_int($baris['Urutan'] ?? null)) {
                    $detail[] = ['KodeJenisPajak' => $baris['KodeJenisPajak'], 'DasarPengenaan' => $baris['DasarPengenaan'], 'Urutan' => $baris['Urutan']];
                }
            }

            $hasil[] = new DataKelompokPajakTemplate(trim($kelompok['Nama']), $detail);
        }

        return $hasil;
    }

    private static function BacaPengaturan(mixed $nilai): DataPengaturanTemplate
    {
        if (! is_array($nilai)) {
            return new DataPengaturanTemplate;
        }

        $pembulatan = is_array($nilai['PembulatanTunai'] ?? null) ? $nilai['PembulatanTunai'] : null;
        $persen = $nilai['PersenBiayaLayanan'] ?? null;

        return new DataPengaturanTemplate(
            pembulatanTunai: $pembulatan !== null && is_int($pembulatan['Kelipatan'] ?? null) && is_string($pembulatan['Arah'] ?? null)
                ? ['Kelipatan' => $pembulatan['Kelipatan'], 'Arah' => $pembulatan['Arah']]
                : null,
            stokBolehMinus: is_bool($nilai['StokBolehMinus'] ?? null) ? $nilai['StokBolehMinus'] : null,
            metodeHpp: is_string($nilai['MetodeHpp'] ?? null) ? $nilai['MetodeHpp'] : null,
            persenBiayaLayanan: is_string($persen) || is_int($persen) ? (string) $persen : null,
            hargaTermasukPajak: is_bool($nilai['HargaTermasukPajak'] ?? null) ? $nilai['HargaTermasukPajak'] : null,
            biayaLayananMasukDpp: is_bool($nilai['BiayaLayananMasukDpp'] ?? null) ? $nilai['BiayaLayananMasukDpp'] : null,
        );
    }

    /**
     * @return list<DataProdukContohTemplate>
     */
    private static function BacaProdukContoh(mixed $nilai): array
    {
        $hasil = [];
        $namaTerlihat = [];

        foreach (is_array($nilai) ? $nilai : [] as $produk) {
            if (! is_array($produk) || ! is_string($produk['Nama'] ?? null) || trim($produk['Nama']) === ''
                || ! is_string($produk['Harga'] ?? null) || preg_match(self::POLA_HARGA, $produk['Harga']) !== 1
                || ! is_string($produk['KodeSatuan'] ?? null)) {
                continue;
            }

            $jenis = is_string($produk['Jenis'] ?? null) ? JenisProduk::tryFrom($produk['Jenis']) : null;
            $nama = trim($produk['Nama']);

            if ($jenis === null || ! $jenis->CekBolehProdukAwal() || isset($namaTerlihat[mb_strtolower($nama)])) {
                continue;
            }

            $namaTerlihat[mb_strtolower($nama)] = true;
            $hasil[] = new DataProdukContohTemplate(
                nama: $nama,
                kategori: is_string($produk['Kategori'] ?? null) && trim($produk['Kategori']) !== '' ? trim($produk['Kategori']) : null,
                harga: $produk['Harga'],
                kodeSatuan: $produk['KodeSatuan'],
                jenis: $jenis,
            );
        }

        return $hasil;
    }
}
