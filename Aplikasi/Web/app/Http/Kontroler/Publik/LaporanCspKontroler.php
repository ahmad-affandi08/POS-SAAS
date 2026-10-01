<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Publik;

use App\Http\Kontroler\Kontroler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Penerima laporan pelanggaran CSP Report-Only (audit PAY-P1-04, `POST /laporan-csp`, format `report-uri`
 * `application/csp-report`). Publik dan tanpa sesi: peramban mengirimnya sendiri. Karena siapa saja bisa mengirim
 * apa saja, isinya dianggap data tak tepercaya: hanya kunci yang dikenal, nilainya dipotong, ukuran badan dibatasi,
 * dan hanya dicatat sebagai peringatan (tidak ada tindakan lain), dengan batas laju per IP.
 */
final class LaporanCspKontroler extends Kontroler
{
    private const KUNCI = ['document-uri', 'violated-directive', 'effective-directive', 'blocked-uri', 'source-file', 'line-number', 'disposition'];

    private const BATAS_BADAN = 8192;

    public function Terima(Request $permintaan): Response
    {
        if (strlen($permintaan->getContent()) <= self::BATAS_BADAN) {
            $laporan = json_decode($permintaan->getContent(), true);
            $isi = is_array($laporan) && is_array($laporan['csp-report'] ?? null) ? $laporan['csp-report'] : [];
            $bersih = [];

            foreach (self::KUNCI as $kunci) {
                if (isset($isi[$kunci]) && (is_string($isi[$kunci]) || is_int($isi[$kunci]))) {
                    $bersih[$kunci] = mb_substr((string) $isi[$kunci], 0, 300);
                }
            }

            if ($bersih !== []) {
                Log::warning('Pelanggaran CSP (report-only).', $bersih);
            }
        }

        return response()->noContent();
    }
}
