<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Whatsapp;

use App\Domain\Integrasi\Whatsapp\Adaptor\AdaptorFonnte;
use App\Domain\Integrasi\Whatsapp\Adaptor\AdaptorMetaCloud;
use App\Domain\Integrasi\Whatsapp\Adaptor\AdaptorStarSender;
use App\Domain\Integrasi\Whatsapp\Adaptor\AdaptorWablas;
use App\Domain\Integrasi\Whatsapp\Adaptor\AdaptorWatzap;

/**
 * Membuat pengirim WhatsApp dari kode penyedia P-05 atau dari konfigurasi aktif (`config('integrasi.Whatsapp')`).
 */
final class PembuatPengirimWhatsapp
{
    /**
     * @param  array<string, string|int>  $pengaturan
     * @param  array<string, string>  $kredensial
     */
    public function Buat(string $penyedia, array $pengaturan, array $kredensial): ?PengirimWhatsapp
    {
        return match ($penyedia) {
            'MetaCloud' => new AdaptorMetaCloud($pengaturan, $kredensial),
            'Fonnte' => new AdaptorFonnte($pengaturan, $kredensial),
            'Wablas' => new AdaptorWablas($pengaturan, $kredensial),
            'StarSender' => new AdaptorStarSender($pengaturan, $kredensial),
            'Watzap' => new AdaptorWatzap($pengaturan, $kredensial),
            default => null,
        };
    }

    public function AmbilAktif(): ?PengirimWhatsapp
    {
        $konfigurasi = config('integrasi.Whatsapp');

        if (! is_array($konfigurasi) || ! is_string($konfigurasi['Penyedia'] ?? null)) {
            return null;
        }

        /** @var array<string, string|int> $pengaturan */
        $pengaturan = is_array($konfigurasi['Pengaturan'] ?? null) ? $konfigurasi['Pengaturan'] : [];
        /** @var array<string, string> $kredensial */
        $kredensial = is_array($konfigurasi['Kredensial'] ?? null) ? $konfigurasi['Kredensial'] : [];

        return $this->Buat($konfigurasi['Penyedia'], $pengaturan, $kredensial);
    }

    /** Nama templat resmi untuk struk digital (hanya WhatsApp Cloud API); null = kirim teks. */
    public function AmbilTemplatStruk(): ?string
    {
        $nama = config('integrasi.Whatsapp.Pengaturan.NamaTemplatStruk');

        return is_string($nama) && $nama !== '' ? $nama : null;
    }

    /** Nama templat resmi untuk pengingat reservasi H-1 (F-07 mode service); null = kirim teks. */
    public function AmbilTemplatPengingatReservasi(): ?string
    {
        $nama = config('integrasi.Whatsapp.Pengaturan.NamaTemplatPengingatReservasi');

        return is_string($nama) && $nama !== '' ? $nama : null;
    }

    /** Nama templat resmi untuk notifikasi cucian siap diambil (laundry §9.9); null = kirim teks. */
    public function AmbilTemplatLaundrySiap(): ?string
    {
        $nama = config('integrasi.Whatsapp.Pengaturan.NamaTemplatLaundrySiap');

        return is_string($nama) && $nama !== '' ? $nama : null;
    }

    /** Nama templat resmi untuk pengingat piutang (D-23 D, hanya WhatsApp Cloud API); null = kirim teks. */
    public function AmbilTemplatPengingatPiutang(): ?string
    {
        $nama = config('integrasi.Whatsapp.Pengaturan.NamaTemplatPengingatPiutang');

        return is_string($nama) && $nama !== '' ? $nama : null;
    }

    /** Nama templat autentikasi untuk kode masuk pembeli toko online (F-17 bagian 3); null = kirim teks. */
    public function AmbilTemplatKodeMasuk(): ?string
    {
        $nama = config('integrasi.Whatsapp.Pengaturan.NamaTemplatKodeMasuk');

        return is_string($nama) && $nama !== '' ? $nama : null;
    }
}
