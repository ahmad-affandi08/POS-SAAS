<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Layanan;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Enum\PelacakanProduk;
use App\Domain\Katalog\Enum\StatusProduk;
use App\Domain\Katalog\Impor\Data\DataPresetImpor;
use App\Domain\Katalog\Layanan\AturanProduk;
use Brick\Math\BigDecimal;
use InvalidArgumentException;

/**
 * Mengurai isi sel impor (F-03 BR-03.6) secara deterministik. Galat = `InvalidArgumentException` berpesan Indonesia.
 *
 * **Aturan angka Indonesia (uang & jumlah)**, berlaku sama untuk semua preset:
 * 1. Spasi dan awalan `Rp`/`IDR` dibuang. Tanda minus atau kurung = negatif → ditolak.
 * 2. Hanya angka, titik, dan koma. **Koma = pemisah desimal** (paling banyak satu). Bagian bulat di depan koma
 *    boleh memakai titik ribuan yang rapi (`1.250.000,50`).
 * 3. Tanpa koma: **satu titik diikuti tepat 2 angka = desimal** untuk uang (`15000.50`); untuk jumlah, satu titik
 *    diikuti 1, 2, atau 4 angka = desimal (`1.5`, `0.25`, `12.5000`). Selain itu titik = pemisah ribuan dan harus
 *    berkelompok 3 angka (`15.000`, `1.250.000`); `15000.5` pada uang ditolak karena ambigu.
 * 4. Uang maksimal 2 desimal & 16 angka bulat; jumlah maksimal 4 desimal & 14 angka bulat. Nol di belakang koma
 *    tidak dihitung (`15.000,00` sah). Tidak ada pembulatan.
 * Sel angka Excel sudah diubah `PembacaBerkasTabel` menjadi teks berkoma desimal, sehingga ikut aturan yang sama.
 */
final class PenguraiNilaiImpor
{
    public static function UraiUang(string $teks): ?Uang
    {
        $angka = self::UraiAngka($teks, 2, [2], 16);

        return $angka === null ? null : Uang::Dari($angka);
    }

    public static function UraiKuantitas(string $teks): ?Kuantitas
    {
        $angka = self::UraiAngka($teks, 4, [1, 2, 4], 14);

        return $angka === null ? null : Kuantitas::Dari($angka);
    }

    /**
     * @param  list<int>  $panjangDesimalTitik  jumlah angka di belakang satu titik yang dianggap desimal
     */
    private static function UraiAngka(string $teks, int $maksimalDesimal, array $panjangDesimalTitik, int $maksimalAngkaBulat): ?BigDecimal
    {
        $bersih = preg_replace('/[\s\x{00A0}]+/u', '', $teks) ?? '';
        $bersih = preg_replace('/^(rp|idr)\.?/i', '', $bersih) ?? '';

        if ($bersih === '') {
            return null;
        }

        if (str_starts_with($bersih, '-') || str_starts_with($bersih, '(')) {
            throw new InvalidArgumentException('Tidak boleh negatif.');
        }

        if (preg_match('/^[0-9.,]+$/', $bersih) !== 1 || substr_count($bersih, ',') > 1) {
            throw new InvalidArgumentException("\"{$teks}\" bukan angka. Contoh: 15000, 15.000, atau 15.000,50.");
        }

        if (str_contains($bersih, ',')) {
            [$bulat, $pecahan] = explode(',', $bersih);

            if ($pecahan === '' || preg_match('/^\d+$/', $pecahan) !== 1 || preg_match('/^(\d+|\d{1,3}(\.\d{3})+)$/', $bulat) !== 1) {
                throw new InvalidArgumentException("Format angka \"{$teks}\" tidak dikenali. Contoh: 15.000,50.");
            }

            $bulat = str_replace('.', '', $bulat);
        } elseif (substr_count($bersih, '.') === 1 && preg_match('/^(\d+)\.(\d+)$/', $bersih, $cocok) === 1 && in_array(strlen($cocok[2]), $panjangDesimalTitik, true)) {
            [$bulat, $pecahan] = [$cocok[1], $cocok[2]];
        } elseif (str_contains($bersih, '.')) {
            if (preg_match('/^\d{1,3}(\.\d{3})+$/', $bersih) !== 1) {
                throw new InvalidArgumentException("Format angka \"{$teks}\" tidak dikenali. Titik dipakai sebagai pemisah ribuan (15.000), koma sebagai desimal (15.000,50).");
            }

            [$bulat, $pecahan] = [str_replace('.', '', $bersih), ''];
        } else {
            [$bulat, $pecahan] = [$bersih, ''];
        }

        $pecahan = rtrim($pecahan, '0');

        if (strlen($pecahan) > $maksimalDesimal) {
            throw new InvalidArgumentException("Maksimal {$maksimalDesimal} angka di belakang koma.");
        }

        $bulat = ltrim($bulat, '0') === '' ? '0' : ltrim($bulat, '0');

        if (strlen($bulat) > $maksimalAngkaBulat) {
            throw new InvalidArgumentException('Angka terlalu besar.');
        }

        return BigDecimal::of($pecahan === '' ? $bulat : "{$bulat}.{$pecahan}");
    }

    /** Ya/Tidak menurut kata preset; kosong = null. */
    public static function UraiBoolean(string $teks, DataPresetImpor $preset): ?bool
    {
        $kecil = mb_strtolower(trim($teks));

        if ($kecil === '') {
            return null;
        }

        if (in_array($kecil, $preset->nilaiBoolean['Ya'], true)) {
            return true;
        }

        if (in_array($kecil, $preset->nilaiBoolean['Tidak'], true)) {
            return false;
        }

        throw new InvalidArgumentException("\"{$teks}\" tidak dikenali. Isi Ya atau Tidak.");
    }

    /** Ya/Tidak/"Ikut outlet" untuk harga termasuk pajak; kosong = null (tidak diubah). */
    public static function UraiTigaKeadaan(string $teks, DataPresetImpor $preset): ?string
    {
        $kecil = mb_strtolower(trim($teks));

        if ($kecil === '') {
            return null;
        }

        if (in_array($kecil, ['ikut', 'ikut outlet', 'ikuti outlet', 'outlet'], true)) {
            return 'Ikut';
        }

        return self::UraiBoolean($teks, $preset) ? 'Ya' : 'Tidak';
    }

    /** Jenis dari nilai enum, label, atau kata preset (tanpa beda huruf besar/kecil). */
    public static function UraiJenis(string $teks, DataPresetImpor $preset): ?JenisProduk
    {
        $kecil = mb_strtolower(trim($teks));

        if ($kecil === '') {
            return null;
        }

        foreach (JenisProduk::cases() as $jenis) {
            if ($kecil === mb_strtolower($jenis->value) || $kecil === mb_strtolower($jenis->AmbilLabel())) {
                return $jenis;
            }
        }

        foreach ($preset->nilaiJenis as $nilai => $kata) {
            if (in_array($kecil, $kata, true)) {
                return JenisProduk::from($nilai);
            }
        }

        throw new InvalidArgumentException("Jenis \"{$teks}\" tidak dikenali. Contoh: Barang stok, Jasa, Bahan baku.");
    }

    public static function UraiPelacakan(string $teks): ?PelacakanProduk
    {
        $kecil = mb_strtolower(trim($teks));

        if ($kecil === '') {
            return null;
        }

        foreach (PelacakanProduk::cases() as $pelacakan) {
            if ($kecil === mb_strtolower($pelacakan->value)) {
                return $pelacakan;
            }
        }

        throw new InvalidArgumentException("Pelacakan \"{$teks}\" tidak dikenali. Isi Tidak, Batch, atau Seri.");
    }

    public static function UraiStatus(string $teks, DataPresetImpor $preset): ?StatusProduk
    {
        $kecil = mb_strtolower(trim($teks));

        if ($kecil === '') {
            return null;
        }

        foreach (StatusProduk::cases() as $status) {
            if ($kecil === mb_strtolower($status->value) || in_array($kecil, $preset->nilaiStatus[$status->value] ?? [], true)) {
                return $status;
            }
        }

        throw new InvalidArgumentException("Status \"{$teks}\" tidak dikenali. Isi Aktif atau Diarsipkan.");
    }

    /**
     * Jalur kategori "Minuman > Kopi" (juga "›"). Maks. `katalog.Kategori.MaksimalKedalaman` tingkat, nama ≤ 60.
     *
     * @return list<string>
     */
    public static function UraiJalurKategori(string $teks): array
    {
        $jalur = array_values(array_filter(array_map('trim', preg_split('/[>›]/u', $teks) ?: []), fn (string $nama): bool => $nama !== ''));
        $maksimal = (int) config('katalog.Kategori.MaksimalKedalaman', 3);

        if (count($jalur) > $maksimal) {
            throw new InvalidArgumentException("Kategori maksimal {$maksimal} tingkat, misal Minuman > Kopi > Kopi Susu.");
        }

        foreach ($jalur as $nama) {
            if (mb_strlen($nama) > 60) {
                throw new InvalidArgumentException('Nama kategori maksimal 60 karakter.');
            }
        }

        return $jalur;
    }

    /**
     * Barcode dipisah `,` `;` `|`; setiap barcode sesuai pola BR-03.1. Duplikat dalam sel dibuang.
     *
     * @return list<string>
     */
    public static function UraiBarcode(string $teks): array
    {
        $hasil = [];

        foreach (preg_split('/[,;|]/', $teks) ?: [] as $barcode) {
            $barcode = trim($barcode);

            if ($barcode === '') {
                continue;
            }

            if (preg_match(AturanProduk::POLA_BARCODE, $barcode) !== 1) {
                throw new InvalidArgumentException("Barcode \"{$barcode}\" tidak valid (3–64 huruf, angka, titik, atau tanda hubung).");
            }

            $hasil[mb_strtolower($barcode)] = $barcode;
        }

        return array_values($hasil);
    }

    /**
     * "Ukuran: M; Warna: Merah" → atribut varian. Teks tanpa pemisah nama-nilai = satu atribut bernama "Varian".
     *
     * @return list<array{Nama: string, Nilai: string}>
     */
    public static function UraiVarian(string $teks, DataPresetImpor $preset): array
    {
        $teks = trim($teks);

        if ($teks === '') {
            return [];
        }

        $pemisahAtribut = $preset->pemisahAtribut !== '' ? $preset->pemisahAtribut : ';';
        $pemisahNamaNilai = $preset->pemisahNamaNilai !== '' ? $preset->pemisahNamaNilai : ':';

        if (! str_contains($teks, $pemisahNamaNilai)) {
            $atribut = [['Nama' => 'Varian', 'Nilai' => $teks]];
        } else {
            $atribut = [];

            foreach (explode($pemisahAtribut, $teks) as $bagian) {
                if (trim($bagian) === '') {
                    continue;
                }

                $pecah = explode($pemisahNamaNilai, $bagian, 2);

                if (count($pecah) !== 2 || trim($pecah[0]) === '' || trim($pecah[1]) === '') {
                    throw new InvalidArgumentException("Varian \"{$teks}\" tidak dikenali. Contoh: Ukuran: M; Warna: Merah.");
                }

                $atribut[] = ['Nama' => trim($pecah[0]), 'Nilai' => trim($pecah[1])];
            }
        }

        $nama = array_map(fn (array $a): string => mb_strtolower($a['Nama']), $atribut);

        if (count($atribut) > (int) config('katalog.Varian.MaksimalAtribut', 3) || count(array_unique($nama)) !== count($nama)) {
            throw new InvalidArgumentException('Varian maksimal '.config('katalog.Varian.MaksimalAtribut', 3).' atribut tanpa nama ganda.');
        }

        foreach ($atribut as $satu) {
            if (mb_strlen($satu['Nama']) > 30 || mb_strlen($satu['Nilai']) > 40) {
                throw new InvalidArgumentException('Nama atribut varian maksimal 30 karakter, nilainya maksimal 40 karakter.');
            }
        }

        return $atribut;
    }

    /** SKU sesuai pola BR-03.1; kosong = null (dibuat otomatis). */
    public static function UraiSku(string $teks): ?string
    {
        $sku = trim($teks);

        if ($sku === '') {
            return null;
        }

        if (preg_match(AturanProduk::POLA_SKU, $sku) !== 1) {
            throw new InvalidArgumentException('SKU maksimal 64 karakter: huruf, angka, titik, garis bawah, tanda hubung, atau garis miring.');
        }

        return $sku;
    }

    /** Bilangan bulat dengan titik ribuan untuk pesan ("20.000"). */
    public static function FormatRibuan(int $angka): string
    {
        return strrev(implode('.', str_split(strrev((string) $angka), 3)));
    }

    /** Uang untuk ekspor: "15000" bila bulat, selain itu "15000.50" (dibaca ulang sebagai desimal). */
    public static function FormatUangEkspor(string $nilai): string
    {
        $uang = Uang::Dari($nilai)->KeString();

        return str_ends_with($uang, '.00') ? substr($uang, 0, -3) : $uang;
    }

    /** Jumlah untuk ekspor tanpa nol di belakang: "12", "1.5", "0.25" (dibaca ulang sebagai desimal). */
    public static function FormatKuantitasEkspor(string $nilai): string
    {
        $teks = (string) BigDecimal::of($nilai)->strippedOfTrailingZeros();

        // "1.500" (3 desimal) akan dibaca sebagai ribuan; tulis 4 desimal agar tetap desimal.
        if (preg_match('/\.\d{3}$/', $teks) === 1) {
            return $teks.'0';
        }

        return $teks;
    }
}
