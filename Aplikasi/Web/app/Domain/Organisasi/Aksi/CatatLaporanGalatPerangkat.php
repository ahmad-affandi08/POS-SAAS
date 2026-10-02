<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Organisasi\Model\Perangkat;
use Illuminate\Support\Facades\Log;

/**
 * K-21 (§17.2.6, §20): laporan galat aplikasi kasir ditulis ke kanal log harian `galat-perangkat` dengan konteks
 * `IdTenant` & `IdPerangkat` (pemantauan mandiri; Sentry SaaS menunggu keputusan pemilik soal transfer data lintas
 * negara). Pesan & jejak disaring lagi di server: email, deret angka panjang (HP, NIK, kartu), dan token bearer diganti
 * `[disamarkan]` supaya data pribadi tidak masuk log meski aplikasi lama lupa menyaring.
 */
final class CatatLaporanGalatPerangkat
{
    /**
     * @param  list<array{Waktu: string, Tingkat: string, Sumber: string, Pesan: string, Jejak?: string|null}>  $galat
     */
    public function Jalankan(Perangkat $perangkat, array $galat, ?string $versi): int
    {
        foreach ($galat as $g) {
            $konteks = [
                'IdTenant' => $perangkat->IdTenant,
                'IdPerangkat' => $perangkat->Id,
                'Versi' => $versi,
                'Sumber' => $g['Sumber'],
                'Waktu' => $g['Waktu'],
                'Jejak' => isset($g['Jejak']) ? self::Saring((string) $g['Jejak']) : null,
            ];
            $pesan = self::Saring($g['Pesan']);

            if ($g['Tingkat'] === 'Peringatan') {
                Log::channel('galat-perangkat')->warning($pesan, $konteks);
            } else {
                Log::channel('galat-perangkat')->error($pesan, $konteks);
            }
        }

        return count($galat);
    }

    /** Samarkan email, token bearer, dan deret ≥ 8 angka (boleh berspasi/strip) seperti nomor HP, NIK, atau kartu. */
    public static function Saring(string $teks): string
    {
        $teks = (string) preg_replace('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', '[disamarkan]', $teks);
        $teks = (string) preg_replace('/Bearer\s+[A-Za-z0-9._~+\/=|-]+/i', 'Bearer [disamarkan]', $teks);

        return (string) preg_replace('/\+?\d(?:[\s-]?\d){7,}/', '[disamarkan]', $teks);
    }
}
