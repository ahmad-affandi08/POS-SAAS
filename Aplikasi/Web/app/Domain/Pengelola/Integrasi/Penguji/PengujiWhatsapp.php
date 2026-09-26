<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Penguji;

use App\Domain\Integrasi\Whatsapp\PembuatPengirimWhatsapp;
use App\Domain\Pengelola\Integrasi\Data\HasilUjiKoneksi;
use Throwable;

/**
 * Tes token/perangkat WhatsApp lewat adaptor runtime (tanpa mengirim pesan).
 */
final class PengujiWhatsapp implements PengujiKoneksiPenyedia
{
    public function __construct(private readonly PembuatPengirimWhatsapp $pembuat) {}

    public function UjiPenyedia(string $penyedia, array $pengaturan, array $kredensial): HasilUjiKoneksi
    {
        $pengirim = $this->pembuat->Buat($penyedia, $pengaturan, $kredensial);

        if ($pengirim === null) {
            return HasilUjiKoneksi::Gagal('Penyedia WhatsApp tidak dikenal.');
        }

        try {
            $hasil = $pengirim->UjiKoneksi();
        } catch (Throwable $galat) {
            return HasilUjiKoneksi::Gagal('Uji gagal: '.PenyaringPesan::Saring($galat->getMessage(), $kredensial));
        }

        return $hasil->berhasil ? HasilUjiKoneksi::Berhasil($hasil->pesan) : HasilUjiKoneksi::Gagal($hasil->pesan);
    }
}
