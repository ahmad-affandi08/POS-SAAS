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
}
