<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Layanan;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (RFC 6238) untuk 2FA wajib Platform Pengelola (BR-P01.2).
 */
final class DuaFaktorPengelola
{
    public const JUMLAH_KODE_PEMULIHAN = 8;

    public function __construct(private readonly Google2FA $google2fa) {}

    public function BuatRahasia(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function BuatUrlOtp(string $email, string $rahasia): string
    {
        return $this->google2fa->getQRCodeUrl((string) config('pengelola.NamaPenerbit2fa'), $email, $rahasia);
    }

    /** QR dirender di server sebagai SVG, sehingga rahasia tidak dikirim ke layanan QR pihak ketiga. */
    public function BuatQrSvg(string $urlOtp): string
    {
        $penulis = new Writer(new ImageRenderer(new RendererStyle(192, 1), new SvgImageBackEnd));

        return $penulis->writeString($urlOtp);
    }

    public function VerifikasiKode(string $rahasia, string $kode): bool
    {
        $kode = preg_replace('/\s+/', '', $kode) ?? '';

        return preg_match('/^\d{6}$/', $kode) === 1
            && $this->google2fa->verifyKey($rahasia, $kode, 1) === true;
    }

    /**
     * @return list<string>
     */
    public function BuatKodePemulihan(): array
    {
        $daftar = [];

        for ($i = 0; $i < self::JUMLAH_KODE_PEMULIHAN; $i++) {
            $daftar[] = Str::upper(Str::random(5).'-'.Str::random(5));
        }

        return $daftar;
    }
}
