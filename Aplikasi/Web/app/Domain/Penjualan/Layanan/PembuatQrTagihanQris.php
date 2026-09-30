<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * F-17 bagian 2: QR (SVG) dari `TagihanQris.IsiQr` untuk halaman bayar toko online. SVG, bukan PNG, supaya tetap
 * tajam saat pelanggan memperbesar layar HP-nya dan bisa ditanam langsung di halaman tanpa permintaan kedua.
 */
final class PembuatQrTagihanQris
{
    public function BuatSvg(string $isiQr, int $ukuran = 280): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle($ukuran, 1), new SvgImageBackEnd)))->writeString($isiQr);
    }
}
