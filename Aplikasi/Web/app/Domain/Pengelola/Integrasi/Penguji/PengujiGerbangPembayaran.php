<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Penguji;

use App\Domain\Integrasi\GerbangPembayaran\GalatGerbang;
use App\Domain\Integrasi\GerbangPembayaran\PembuatGerbangPembayaran;
use App\Domain\Pengelola\Integrasi\Data\HasilUjiKoneksi;
use Throwable;

/**
 * Tes kredensial gerbang pembayaran lewat adaptor runtime (tanpa membuat transaksi).
 */
final class PengujiGerbangPembayaran implements PengujiKoneksiPenyedia
{
    public function __construct(private readonly PembuatGerbangPembayaran $pembuat) {}

    public function UjiPenyedia(string $penyedia, array $pengaturan, array $kredensial): HasilUjiKoneksi
    {
        $gerbang = $this->pembuat->Buat($penyedia, $pengaturan, $kredensial);

        if ($gerbang === null) {
            return HasilUjiKoneksi::Gagal('Penyedia gerbang pembayaran tidak dikenal.');
        }

        try {
            $hasil = $gerbang->UjiKoneksi();
        } catch (GalatGerbang $galat) {
            return HasilUjiKoneksi::Gagal($galat->getMessage());
        } catch (Throwable $galat) {
            return HasilUjiKoneksi::Gagal('Uji gagal: '.PenyaringPesan::Saring($galat->getMessage(), $kredensial));
        }

        return $hasil->berhasil ? HasilUjiKoneksi::Berhasil($hasil->pesan) : HasilUjiKoneksi::Gagal($hasil->pesan);
    }
}
