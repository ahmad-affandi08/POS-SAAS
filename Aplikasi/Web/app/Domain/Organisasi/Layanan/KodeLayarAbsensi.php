<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Layanan;

use App\Domain\Organisasi\Model\Outlet;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\CarbonInterface;

/**
 * F-18 bagian 4 (D-37): kode 6 digit layar QR absensi outlet, berganti tiap {@see self::DETIK_JENDELA} detik. Kode =
 * HMAC-SHA256 dari rahasia layar outlet atas nomor jendela waktu (gaya TOTP), dipotong ke 6 digit. Kode jendela
 * sebelumnya masih diterima supaya karyawan yang memindai di detik terakhir tidak ditolak (jarak HP–server).
 */
final class KodeLayarAbsensi
{
    public const DETIK_JENDELA = 30;

    public const PANJANG_TOKEN = 40;

    /** @return array{Kode: string, BerlakuSampai: string, Qr: string} */
    public function AmbilUntukLayar(string $token, CarbonInterface $waktu): array
    {
        $jendela = self::HitungJendela($waktu);
        $kode = self::HitungKode($token, $jendela);

        return [
            'Kode' => $kode,
            'BerlakuSampai' => $waktu->copy()->setTimestamp(($jendela + 1) * self::DETIK_JENDELA)->utc()->toIso8601String(),
            'Qr' => (new Writer(new ImageRenderer(new RendererStyle(320, 1), new SvgImageBackEnd)))->writeString($kode),
        ];
    }

    /** Cocokkan kode dari karyawan dengan jendela sekarang atau sebelumnya. Outlet tanpa layar = selalu gagal. */
    public function Cocokkan(Outlet $outlet, string $kode, CarbonInterface $waktu): bool
    {
        $token = $outlet->TokenLayarAbsen;

        if (! is_string($token) || preg_match('/^\d{6}$/', $kode) !== 1) {
            return false;
        }

        $jendela = self::HitungJendela($waktu);

        foreach ([$jendela, $jendela - 1] as $j) {
            if (hash_equals(self::HitungKode($token, $j), $kode)) {
                return true;
            }
        }

        return false;
    }

    public static function Hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private static function HitungJendela(CarbonInterface $waktu): int
    {
        return intdiv($waktu->getTimestamp(), self::DETIK_JENDELA);
    }

    private static function HitungKode(string $token, int $jendela): string
    {
        $hmac = hash_hmac('sha256', 'absen:'.$jendela, $token, true);
        $geser = ord($hmac[31]) & 0x0F;
        $angka = ((ord($hmac[$geser]) & 0x7F) << 24) | (ord($hmac[$geser + 1]) << 16) | (ord($hmac[$geser + 2]) << 8) | ord($hmac[$geser + 3]);

        return str_pad((string) ($angka % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
