<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Layanan;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor laporan F-14a ke CSV (UTF-8 dengan BOM agar Excel membaca huruf Indonesia, pemisah koma), dialirkan langsung
 * ke respons. Angka ditulis sebagai desimal bertitik apa adanya (bisa diolah lembar kerja). Anti formula injection:
 * teks yang diawali `=`, `+`, `-`, `@`, tab, atau CR (dan bukan angka) diberi awalan `'`.
 */
final class PenulisCsvLaporan
{
    /**
     * @param  list<string>  $judul
     * @param  iterable<list<string|int|null>>  $baris
     */
    public static function Alirkan(string $namaBerkas, array $judul, iterable $baris): StreamedResponse
    {
        return new StreamedResponse(function () use ($judul, $baris): void {
            $keluaran = fopen('php://output', 'wb');

            if ($keluaran === false) {
                return;
            }

            fwrite($keluaran, "\xEF\xBB\xBF");
            fputcsv($keluaran, array_map(self::Netralkan(...), $judul), ',', '"', '');

            foreach ($baris as $isi) {
                fputcsv($keluaran, array_map(fn (string|int|null $nilai): string => self::Netralkan((string) $nilai), $isi), ',', '"', '');
            }

            fclose($keluaran);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$namaBerkas.'.csv"',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function Netralkan(string $nilai): string
    {
        if ($nilai === '' || preg_match('/^-?\d+(\.\d+)?$/', $nilai) === 1) {
            return $nilai;
        }

        return in_array($nilai[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$nilai : $nilai;
    }
}
