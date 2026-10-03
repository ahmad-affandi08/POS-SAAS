<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Layanan;

use App\Domain\Lisensi\Enum\EdisiAplikasi;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * QR kode aktivasi perangkat untuk dipindai aplikasi POS (F-02 langkah 5). Isi QR = kode 8 karakter apa adanya,
 * sama dengan yang bisa diketik manual; alamat server sudah tertanam di aplikasi.
 *
 * D-35 edisi Lisensi: aplikasi yang sama dipakai banyak server pembeli, jadi QR membawa alamat server juga dalam bentuk
 * `{APP_URL}/aktivasi-perangkat?kode={kode}`. Aplikasi mengambil alamat dasar & kode dari URL itu; kode tetap bisa
 * diketik manual bersama alamat server di layar aktivasi.
 */
final class PembuatQrKodeAktivasi
{
    public function BuatSvg(string $kode): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle(200, 2), new SvgImageBackEnd)))->writeString(self::AmbilIsi($kode));
    }

    public static function AmbilIsi(string $kode): string
    {
        if (! EdisiAplikasi::CekLisensi()) {
            return $kode;
        }

        $alamat = config('app.url');

        return rtrim(is_string($alamat) ? $alamat : '', '/').'/aktivasi-perangkat?kode='.rawurlencode($kode);
    }
}
