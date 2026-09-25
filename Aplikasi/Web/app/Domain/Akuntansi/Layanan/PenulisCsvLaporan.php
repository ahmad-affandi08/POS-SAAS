<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Layanan;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor CSV laporan keuangan F-13a, dialirkan langsung ke respons. UTF-8 dengan BOM (Excel membaca huruf Indonesia),
 * pemisah koma, uang sebagai angka desimal titik (`15000.00`) agar bisa dijumlah di lembar kerja. **Anti formula
 * injection**: teks yang diawali `=`, `+`, `-`, `@`, tab, atau CR diberi awalan `'`, kecuali angka desimal murni.
 */
final class PenulisCsvLaporan
{
    /**
     * @param  list<string>  $judul
     * @param  iterable<list<string|null>>  $baris
     */
    public static function Alirkan(string $namaBerkas, array $judul, iterable $baris): StreamedResponse
    {
        return new StreamedResponse(function () use ($judul, $baris): void {
            $keluaran = fopen('php://output', 'wb');

            if ($keluaran === false) {
                return;
            }

            fwrite($keluaran, "\xEF\xBB\xBF");
            fputcsv($keluaran, array_map(self::NetralkanRumus(...), $judul), ',', '"', '');

            foreach ($baris as $isi) {
                fputcsv($keluaran, array_map(fn (?string $nilai): string => self::NetralkanRumus((string) $nilai), $isi), ',', '"', '');
            }

            fclose($keluaran);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$namaBerkas.'.csv"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function NetralkanRumus(string $nilai): string
    {
        if (preg_match('/^-?\d+(\.\d+)?$/', $nilai) === 1) {
            return $nilai;
        }

        return $nilai !== '' && in_array($nilai[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$nilai : $nilai;
    }
}
