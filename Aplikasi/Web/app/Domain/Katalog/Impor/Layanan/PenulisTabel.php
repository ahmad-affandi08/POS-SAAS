<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Layanan;

use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Options as OpsiCsv;
use OpenSpout\Writer\CSV\Writer as PenulisCsv;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Writer as PenulisXlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Penulis lembar kerja unduhan (F-03: templat, laporan impor, ekspor produk) berbasis openspout, dialirkan
 * (streaming) langsung ke respons tanpa berkas sementara di disk aplikasi.
 * - Semua sel ditulis sebagai teks. **Anti formula injection**: sel yang diawali `=`, `+`, `-`, `@`, tab, atau CR
 *   diberi awalan `'` sehingga Excel/Sheets tidak menjalankannya sebagai rumus (dibuang lagi saat diimpor).
 * - CSV: UTF-8 dengan BOM (Excel membaca huruf Indonesia dengan benar), pemisah koma.
 */
final class PenulisTabel
{
    public const TIPE_KONTEN = [
        PembacaBerkasTabel::FORMAT_XLSX => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        PembacaBerkasTabel::FORMAT_CSV => 'text/csv; charset=UTF-8',
    ];

    /**
     * @param  list<string>  $judul
     * @param  callable(): iterable<list<string|null>>  $baris  dipanggil saat respons dialirkan
     */
    public static function Alirkan(string $format, string $namaBerkas, array $judul, callable $baris): StreamedResponse
    {
        $format = $format === PembacaBerkasTabel::FORMAT_CSV ? PembacaBerkasTabel::FORMAT_CSV : PembacaBerkasTabel::FORMAT_XLSX;

        return new StreamedResponse(function () use ($format, $judul, $baris): void {
            $penulis = self::BuatPenulis($format);
            $penulis->openToFile('php://output');
            $penulis->addRow(self::BuatBaris($judul, (new Style)->setFontBold()));

            foreach ($baris() as $isi) {
                $penulis->addRow(self::BuatBaris($isi));
            }

            $penulis->close();
        }, 200, [
            'Content-Type' => self::TIPE_KONTEN[$format],
            'Content-Disposition' => 'attachment; filename="'.$namaBerkas.'.'.$format.'"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Awali `'` bila sel bisa dibaca sebagai rumus oleh aplikasi lembar kerja. */
    public static function NetralkanRumus(string $nilai): string
    {
        return $nilai !== '' && in_array($nilai[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$nilai : $nilai;
    }

    private static function BuatPenulis(string $format): WriterInterface
    {
        if ($format === PembacaBerkasTabel::FORMAT_CSV) {
            $opsi = new OpsiCsv;
            $opsi->SHOULD_ADD_BOM = true;

            return new PenulisCsv($opsi);
        }

        return new PenulisXlsx;
    }

    /**
     * @param  list<string|null>  $isi
     */
    private static function BuatBaris(array $isi, ?Style $gaya = null): Row
    {
        return new Row(array_map(fn (?string $nilai): StringCell => new StringCell(self::NetralkanRumus((string) $nilai), null), $isi), $gaya);
    }
}
