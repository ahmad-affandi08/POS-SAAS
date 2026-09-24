<?php

declare(strict_types=1);

namespace Tests\Pendukung\Katalog;

use App\Domain\Katalog\Impor\Enum\BidangImpor;
use App\Domain\Katalog\Impor\Model\ImporProduk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader as PembacaXlsx;
use OpenSpout\Writer\XLSX\Writer as PenulisXlsx;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Prasyarat test impor/ekspor produk F-03 Tim 4: penulis berkas uji xlsx/csv (dibuat saat test, tanpa berkas biner
 * di repo), unggah + pemetaan + terapkan lewat rute, dan pembaca isi unduhan xlsx/csv.
 */
final class BantuanImpor
{
    /** Judul kolom templat Umum (sama dengan ekspor). */
    public static function Judul(BidangImpor $bidang): string
    {
        return $bidang->AmbilJudul();
    }

    /**
     * Berkas xlsx uji: baris pertama = judul. Nilai int ditulis sebagai sel angka, string sebagai teks.
     *
     * @param  list<list<string|int|null>>  $baris
     */
    public static function BuatXlsx(array $baris, string $nama = 'produk.xlsx'): UploadedFile
    {
        $path = self::PathSementara('xlsx');
        $penulis = new PenulisXlsx;
        $penulis->openToFile($path);

        foreach ($baris as $isi) {
            $penulis->addRow(Row::fromValues($isi));
        }

        $penulis->close();

        return new UploadedFile($path, $nama, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    /**
     * Berkas CSV uji dengan pemisah, BOM, dan pengodean tertentu.
     *
     * @param  list<list<string|int|null>>  $baris
     */
    public static function BuatCsv(array $baris, string $pemisah = ',', bool $bom = false, string $pengodean = 'UTF-8', string $nama = 'produk.csv'): UploadedFile
    {
        $teks = '';

        foreach ($baris as $isi) {
            $teks .= implode($pemisah, array_map(function (string|int|null $sel) use ($pemisah): string {
                $sel = (string) $sel;

                return str_contains($sel, $pemisah) || str_contains($sel, '"') ? '"'.str_replace('"', '""', $sel).'"' : $sel;
            }, $isi))."\r\n";
        }

        if ($pengodean !== 'UTF-8') {
            $teks = (string) mb_convert_encoding($teks, $pengodean, 'UTF-8');
        }

        return self::BuatBerkasMentah(($bom ? "\xEF\xBB\xBF" : '').$teks, $nama);
    }

    public static function BuatBerkasMentah(string $isi, string $nama): UploadedFile
    {
        $path = self::PathSementara(pathinfo($nama, PATHINFO_EXTENSION) ?: 'bin');
        file_put_contents($path, $isi);

        return new UploadedFile($path, $nama, null, null, true);
    }

    /**
     * Unggah lewat rute; mengembalikan impor terbaru tenant aktif.
     */
    public static function Unggah(TestCase $tes, UploadedFile $berkas, string $sumber = 'Umum'): ImporProduk
    {
        $tes->post('/kelola/produk/impor', ['Berkas' => $berkas, 'Sumber' => $sumber])->assertSessionHasNoErrors()->assertRedirect();

        return ImporProduk::query()->orderByDesc('Id')->firstOrFail();
    }

    /**
     * Simpan pemetaan (bawaan = pemetaan otomatis) dan opsi, lalu kembalikan impor terbaru.
     *
     * @param  array<string, mixed>  $opsi
     * @param  array<string, int|null>|null  $pemetaan
     * @return TestResponse<Response>
     */
    public static function Petakan(TestCase $tes, ImporProduk $impor, array $opsi = [], ?array $pemetaan = null): TestResponse
    {
        return $tes->put("/kelola/produk/impor/{$impor->Uuid}/pemetaan", [
            'Pemetaan' => $pemetaan ?? $impor->Pemetaan,
            'Opsi' => array_replace([
                'Mode' => 'TambahDanPerbarui',
                'UuidKelompokPajakBawaan' => null,
                'JenisBawaan' => 'Stok',
                'BuatKategoriBaru' => true,
                'BuatSatuanBaru' => true,
            ], $opsi),
        ]);
    }

    /**
     * Isi unduhan xlsx/csv sebagai baris teks.
     *
     * @param  TestResponse<Response>  $respons
     * @return list<list<string>>
     */
    public static function BacaUnduhan(TestResponse $respons, string $format = 'xlsx'): array
    {
        $isi = $respons->streamedContent();

        if ($format === 'csv') {
            $isi = str_starts_with($isi, "\xEF\xBB\xBF") ? substr($isi, 3) : $isi;
            $hasil = [];

            foreach (preg_split('/\r\n|\n/', trim($isi)) ?: [] as $baris) {
                $hasil[] = array_map('strval', str_getcsv($baris, ',', '"', ''));
            }

            return $hasil;
        }

        $path = self::PathSementara('xlsx');
        file_put_contents($path, $isi);
        $pembaca = new PembacaXlsx;
        $pembaca->open($path);
        $hasil = [];

        foreach ($pembaca->getSheetIterator() as $lembar) {
            foreach ($lembar->getRowIterator() as $baris) {
                $hasil[] = array_map(fn (mixed $sel): string => is_scalar($sel) ? (string) $sel : '', $baris->toArray());
            }

            break;
        }

        $pembaca->close();

        return $hasil;
    }

    private static function PathSementara(string $ekstensi): string
    {
        $folder = storage_path('framework/testing/impor');

        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        return $folder.'/'.Str::lower((string) Str::ulid()).'.'.$ekstensi;
    }
}
