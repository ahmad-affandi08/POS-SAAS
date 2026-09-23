<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Layanan;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR kode aktivasi perangkat untuk dipindai aplikasi POS (F-02 langkah 5). Isi QR = kode 8 karakter apa adanya,
 * sama dengan yang bisa diketik manual; alamat server sudah tertanam di aplikasi.
 */
final class PembuatQrKodeAktivasi
{
    public function BuatSvg(string $kode): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle(200, 2), new SvgImageBackEnd)))->writeString($kode);
    }
}
