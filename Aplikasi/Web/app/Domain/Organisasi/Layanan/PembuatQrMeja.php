<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Layanan;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * F-17: QR (SVG) berisi URL publik pesan sendiri meja `/{slugTenant}/meja/{token}`, untuk dialog & kartu meja cetak.
 */
final class PembuatQrMeja
{
    public function BuatSvg(string $url): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle(240, 1), new SvgImageBackEnd)))->writeString($url);
    }
}
