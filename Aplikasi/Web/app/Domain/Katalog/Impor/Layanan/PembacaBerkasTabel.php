<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use Brick\Math\BigDecimal;
use DateTimeInterface;
use Generator;
use OpenSpout\Reader\CSV\Options as OpsiCsv;
use OpenSpout\Reader\CSV\Reader as PembacaCsv;
use OpenSpout\Reader\CSV\Sheet as LembarCsv;
use OpenSpout\Reader\XLSX\Options as OpsiXlsx;
use OpenSpout\Reader\XLSX\Reader as PembacaXlsx;
use OpenSpout\Reader\XLSX\Sheet as LembarXlsx;
use Throwable;
use ZipArchive;

/**
 * Pembaca berkas impor Excel (.xlsx) & CSV (F-03 BR-03.6) berbasis openspout: baris demi baris dengan memori tetap.
 *
 * - **Jenis berkas ditentukan dari isi, bukan nama**: xlsx = arsip ZIP berisi `[Content_Types].xml` dan
 *   `xl/workbook.xml`; csv = teks (tanpa bita kendali biner). Ekstensi nama berkas wajib sama dengan hasil deteksi.
 *   Berkas .xls lama, ZIP lain, gambar/PDF ditolak (`BerkasImporTidakValid`).
 * - CSV dinormalisasi saat unggah: BOM dibuang, UTF-16 (BOM) diubah ke UTF-8, teks yang bukan UTF-8 sah dianggap
 *   Windows-1252. Pemisah = yang terbanyak dari `,` `;` tab di baris pertama.
 * - Nilai sel dijadikan teks: angka bulat apa adanya; angka pecahan ditulis dengan **koma desimal** ("15000,5")
 *   agar tidak ambigu bagi `PenguraiNilaiImpor` (titik = pemisah ribuan); tanggal `Y-m-d`; boolean `true`/`false`.
 *   Angka sel dikonversi lewat `BigDecimal` dari representasi teksnya, tanpa pembulatan.
 * - Nomor baris = nomor baris spreadsheet (baris kosong tetap dihitung, tetapi tidak dikembalikan).
 */
final class PembacaBerkasTabel
{
    public const FORMAT_XLSX = 'xlsx';

    public const FORMAT_CSV = 'csv';

    /** Batas jumlah kolom yang dibaca (sisanya diabaikan). */
    public const MAKSIMAL_KOLOM = 100;

    /** Batas bawaan total isi ZIP xlsx setelah diekstrak (perlindungan bom ZIP), KB; `katalog.Impor.UkuranEkstrakMaksimalKb`. */
    private const MAKSIMAL_UKURAN_EKSTRAK_KB = 204800;

    /**
     * Jenis berkas dari isinya. Ekstensi `namaAsli` harus xlsx/csv dan sama dengan isinya.
     *
     * @throws PelanggaranAturanBisnis `BerkasImporTidakValid`
     */
    public function TentukanFormat(string $path, string $namaAsli): string
    {
        $ekstensi = mb_strtolower(pathinfo($namaAsli, PATHINFO_EXTENSION));

        if (! in_array($ekstensi, [self::FORMAT_XLSX, self::FORMAT_CSV], true)) {
            throw self::GalatBerkas('Format berkas harus .xlsx atau .csv.');
        }

        $awal = (string) file_get_contents($path, false, null, 0, 65536);

        if ($awal === '') {
            throw self::GalatBerkas('Berkas kosong.');
        }

        if (str_starts_with($awal, "\xD0\xCF\x11\xE0")) {
            throw self::GalatBerkas('Berkas Excel lama (.xls) belum didukung. Simpan ulang sebagai .xlsx lalu unggah lagi.');
        }

        if (str_starts_with($awal, "PK\x03\x04")) {
            if (! self::CekXlsx($path)) {
                throw self::GalatBerkas('Isi berkas bukan lembar kerja Excel .xlsx yang sah.');
            }

            $format = self::FORMAT_XLSX;
        } elseif (self::CekTeks($awal)) {
            $format = self::FORMAT_CSV;
        } else {
            throw self::GalatBerkas('Isi berkas bukan Excel .xlsx atau teks CSV.');
        }

        if ($format !== $ekstensi) {
            throw self::GalatBerkas("Isi berkas adalah {$format}, tidak sesuai dengan nama berkas .{$ekstensi}.");
        }

        return $format;
    }

    /** Isi CSV menjadi UTF-8 tanpa BOM (UTF-16 ber-BOM diubah; bukan UTF-8 sah dianggap Windows-1252). */
    public function NormalisasiCsv(string $isi): string
    {
        if (str_starts_with($isi, "\xFF\xFE")) {
            return (string) mb_convert_encoding(substr($isi, 2), 'UTF-8', 'UTF-16LE');
        }

        if (str_starts_with($isi, "\xFE\xFF")) {
            return (string) mb_convert_encoding(substr($isi, 2), 'UTF-8', 'UTF-16BE');
        }

        if (str_starts_with($isi, "\xEF\xBB\xBF")) {
            $isi = substr($isi, 3);
        }

        return mb_check_encoding($isi, 'UTF-8') ? $isi : (string) mb_convert_encoding($isi, 'UTF-8', 'Windows-1252');
    }

    /** Pemisah CSV = karakter terbanyak dari `,` `;` tab di baris pertama yang berisi (seri: koma). */
    public function DeteksiPemisah(string $isiUtf8): string
    {
        $barisPertama = '';

        foreach (preg_split('/\r\n|\n|\r/', substr($isiUtf8, 0, 65536)) ?: [] as $baris) {
            if (trim($baris) !== '') {
                $barisPertama = $baris;
                break;
            }
        }

        $jumlah = [',' => substr_count($barisPertama, ','), ';' => substr_count($barisPertama, ';'), "\t" => substr_count($barisPertama, "\t")];
        arsort($jumlah);
        $pemisah = (string) array_key_first($jumlah);

        return $jumlah[$pemisah] > 0 ? $pemisah : ',';
    }

    /**
     * Baris berisi (bukan kosong) dengan nomor baris spreadsheet sebagai kunci. Setiap sel sudah berupa teks.
     *
     * @return Generator<int, list<string>>
     */
    public function BacaBaris(string $path, string $format, ?string $pemisahCsv = null, ?string $lembar = null): Generator
    {
        $pembaca = $this->Buka($path, $format, $pemisahCsv);

        try {
            $sheet = self::PilihLembar($pembaca, $lembar);

            if ($sheet === null) {
                return;
            }

            $nomor = 0;

            foreach ($sheet->getRowIterator() as $baris) {
                $nomor++;
                $sel = array_map(fn (mixed $nilai): string => self::KeTeks($nilai), array_slice($baris->toArray(), 0, self::MAKSIMAL_KOLOM));

                while ($sel !== [] && end($sel) === '') {
                    array_pop($sel);
                }

                if ($sel !== []) {
                    yield $nomor => $sel;
                }
            }
        } finally {
            $pembaca->close();
        }
    }

    /**
     * Membaca judul kolom, contoh isi, dan jumlah baris data (untuk pemetaan & batas baris).
     * Baris judul = `barisJudul` bila diisi, selain itu baris pertama dari 10 baris awal yang punya ≥ 2 sel berisi
     * (atau baris berisi pertama).
     *
     * @return array{BarisJudul: int, KolomSumber: list<array{Indeks: int, Judul: string, Contoh: list<string>}>, JumlahBaris: int}
     *
     * @throws PelanggaranAturanBisnis `BerkasImporTidakValid` (tanpa judul/data), `BarisTerlaluBanyak`
     */
    public function BacaKepala(string $path, string $format, ?string $pemisahCsv, ?string $lembar, ?int $barisJudul, int $maksimalBaris): array
    {
        $awal = [];
        $judulTerpilih = null;
        $kolomSumber = [];
        $jumlahData = 0;

        foreach ($this->BacaBaris($path, $format, $pemisahCsv, $lembar) as $nomor => $sel) {
            if ($judulTerpilih === null) {
                if ($barisJudul !== null) {
                    if ($nomor < $barisJudul) {
                        continue;
                    }

                    $judulTerpilih = $nomor;
                    $kolomSumber = self::SusunKolom($sel);

                    continue;
                }

                $awal[$nomor] = $sel;

                if (count(array_filter($sel, fn (string $isi): bool => $isi !== '')) >= 2 || $nomor >= 10) {
                    $judulTerpilih = count(array_filter($sel, fn (string $isi): bool => $isi !== '')) >= 2 ? $nomor : (int) array_key_first($awal);
                    $kolomSumber = self::SusunKolom($awal[$judulTerpilih]);

                    foreach ($awal as $nomorAwal => $selAwal) {
                        if ($nomorAwal > $judulTerpilih) {
                            $jumlahData++;
                            self::TambahContoh($kolomSumber, $selAwal);
                        }
                    }
                }

                continue;
            }

            $jumlahData++;

            if ($jumlahData > $maksimalBaris) {
                throw self::GalatBarisTerlaluBanyak($maksimalBaris);
            }

            self::TambahContoh($kolomSumber, $sel);
        }

        if ($judulTerpilih === null && $awal !== []) {
            $judulTerpilih = (int) array_key_first($awal);
            $kolomSumber = self::SusunKolom($awal[$judulTerpilih]);

            foreach ($awal as $nomorAwal => $selAwal) {
                if ($nomorAwal > $judulTerpilih) {
                    $jumlahData++;
                    self::TambahContoh($kolomSumber, $selAwal);
                }
            }
        }

        if ($judulTerpilih === null || $kolomSumber === []) {
            throw self::GalatBerkas('Berkas tidak berisi baris judul kolom.');
        }

        if ($jumlahData > $maksimalBaris) {
            throw self::GalatBarisTerlaluBanyak($maksimalBaris);
        }

        if ($jumlahData === 0) {
            throw self::GalatBerkas('Berkas tidak berisi baris produk di bawah judul kolom.');
        }

        return ['BarisJudul' => $judulTerpilih, 'KolomSumber' => $kolomSumber, 'JumlahBaris' => $jumlahData];
    }

    private function Buka(string $path, string $format, ?string $pemisahCsv): PembacaCsv|PembacaXlsx
    {
        if ($format === self::FORMAT_CSV) {
            $opsi = new OpsiCsv;
            $opsi->FIELD_DELIMITER = $pemisahCsv ?? ',';
            $opsi->SHOULD_PRESERVE_EMPTY_ROWS = true;
            $pembaca = new PembacaCsv($opsi);
        } else {
            $opsi = new OpsiXlsx;
            $opsi->SHOULD_PRESERVE_EMPTY_ROWS = true;
            $pembaca = new PembacaXlsx($opsi);
        }

        try {
            $pembaca->open($path);
        } catch (Throwable) {
            throw self::GalatBerkas('Berkas tidak bisa dibaca. Pastikan berkas tidak rusak atau terkunci kata sandi.');
        }

        return $pembaca;
    }

    private static function PilihLembar(PembacaCsv|PembacaXlsx $pembaca, ?string $lembar): LembarCsv|LembarXlsx|null
    {
        $pertama = null;

        foreach ($pembaca->getSheetIterator() as $sheet) {
            $pertama ??= $sheet;

            if ($lembar === null || mb_strtolower($sheet->getName()) === mb_strtolower($lembar)) {
                return $sheet;
            }
        }

        return $pertama;
    }

    /**
     * Judul kolom unik: judul kosong = "Kolom {n}", judul ganda diberi akhiran " (2)".
     *
     * @param  list<string>  $sel
     * @return list<array{Indeks: int, Judul: string, Contoh: list<string>}>
     */
    private static function SusunKolom(array $sel): array
    {
        $kolom = [];
        $dipakai = [];

        foreach ($sel as $indeks => $isi) {
            $judul = mb_substr(preg_replace('/\s+/u', ' ', $isi) ?? $isi, 0, 100);
            $judul = $judul === '' ? 'Kolom '.($indeks + 1) : $judul;
            $dasar = $judul;
            $ke = 2;

            while (isset($dipakai[mb_strtolower($judul)])) {
                $judul = "{$dasar} ({$ke})";
                $ke++;
            }

            $dipakai[mb_strtolower($judul)] = true;
            $kolom[] = ['Indeks' => $indeks, 'Judul' => $judul, 'Contoh' => []];
        }

        return $kolom;
    }

    /**
     * @param  list<array{Indeks: int, Judul: string, Contoh: list<string>}>  $kolom
     * @param  list<string>  $sel
     */
    private static function TambahContoh(array &$kolom, array $sel): void
    {
        foreach ($kolom as $i => $satu) {
            $isi = $sel[$satu['Indeks']] ?? '';

            if ($isi !== '' && count($satu['Contoh']) < 3) {
                $kolom[$i]['Contoh'][] = mb_substr($isi, 0, 60);
            }
        }
    }

    public static function KeTeks(mixed $nilai): string
    {
        if ($nilai === null) {
            return '';
        }

        if (is_string($nilai)) {
            $teks = trim(str_replace("\u{00A0}", ' ', $nilai));

            // Kebalikan penetralan rumus ekspor ('=…, '+…, '-…, '@…) agar ekspor → impor kembali utuh.
            return preg_match('/^\'[=+\-@]/', $teks) === 1 ? substr($teks, 1) : $teks;
        }

        if (is_bool($nilai)) {
            return $nilai ? 'true' : 'false';
        }

        if (is_int($nilai)) {
            return (string) $nilai;
        }

        if ($nilai instanceof DateTimeInterface) {
            return $nilai->format('Y-m-d');
        }

        if (is_numeric($nilai)) {
            // Angka pecahan dari sel: teks desimal persis (tanpa pembulatan), koma sebagai pemisah desimal.
            return str_replace('.', ',', (string) BigDecimal::of((string) $nilai)->strippedOfTrailingZeros());
        }

        return '';
    }

    private static function CekXlsx(string $path): bool
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return false;
        }

        try {
            if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName('xl/workbook.xml') === false) {
                return false;
            }

            // Ukuran di direktori pusat bisa dipalsukan (libzip tetap mengekstrak isi sebenarnya), jadi isi setiap
            // entri benar-benar dibaca dan dihitung; berhenti begitu melewati batas.
            $sisa = self::AmbilUkuranEkstrakMaksimal();

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $aliran = $zip->getStreamIndex($i);

                if (! is_resource($aliran)) {
                    return false;
                }

                try {
                    while (! feof($aliran)) {
                        $potongan = fread($aliran, 1048576);

                        if ($potongan === false) {
                            return false;
                        }

                        $sisa -= strlen($potongan);

                        if ($sisa < 0) {
                            return false;
                        }

                        if ($potongan === '') {
                            break;
                        }
                    }
                } finally {
                    fclose($aliran);
                }
            }

            return true;
        } finally {
            $zip->close();
        }
    }

    private static function AmbilUkuranEkstrakMaksimal(): int
    {
        return (int) config('katalog.Impor.UkuranEkstrakMaksimalKb', self::MAKSIMAL_UKURAN_EKSTRAK_KB) * 1024;
    }

    /** Teks bila tidak ada bita kendali biner (selain tab, LF, CR, FF) atau diawali BOM UTF-16. */
    private static function CekTeks(string $awal): bool
    {
        if (str_starts_with($awal, "\xFF\xFE") || str_starts_with($awal, "\xFE\xFF")) {
            return true;
        }

        return preg_match('/[\x00-\x08\x0B\x0E-\x1F\x7F]/', $awal) !== 1;
    }

    private static function GalatBarisTerlaluBanyak(int $maksimalBaris): PelanggaranAturanBisnis
    {
        return new PelanggaranAturanBisnis('BarisTerlaluBanyak', 'Berkas berisi lebih dari '.PenguraiNilaiImpor::FormatRibuan($maksimalBaris).' baris produk. Bagi berkas menjadi beberapa bagian.', 'Berkas');
    }

    private static function GalatBerkas(string $pesan): PelanggaranAturanBisnis
    {
        return new PelanggaranAturanBisnis('BerkasImporTidakValid', $pesan, 'Berkas');
    }
}
