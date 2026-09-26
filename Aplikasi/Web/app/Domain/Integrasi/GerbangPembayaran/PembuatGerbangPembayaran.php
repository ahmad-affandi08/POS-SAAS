<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\GerbangPembayaran;

use App\Domain\Integrasi\GerbangPembayaran\Adaptor\AdaptorDoku;
use App\Domain\Integrasi\GerbangPembayaran\Adaptor\AdaptorDuitku;
use App\Domain\Integrasi\GerbangPembayaran\Adaptor\AdaptorIpaymu;
use App\Domain\Integrasi\GerbangPembayaran\Adaptor\AdaptorMidtrans;
use App\Domain\Integrasi\GerbangPembayaran\Adaptor\AdaptorTripay;
use App\Domain\Integrasi\GerbangPembayaran\Adaptor\AdaptorXendit;

/**
 * Membuat adaptor gerbang pembayaran dari kode penyedia (nilai `PenyediaIntegrasi` P-05) atau dari konfigurasi aktif
 * lingkungan server (`config('integrasi.GerbangPembayaran')`, dipasang `PenerapKonfigurasiIntegrasi`).
 */
final class PembuatGerbangPembayaran
{
    /** @var list<string> */
    public const PENYEDIA = ['Midtrans', 'Xendit', 'Tripay', 'Duitku', 'Ipaymu', 'Doku'];

    /**
     * @param  array<string, string|int>  $pengaturan
     * @param  array<string, string>  $kredensial
     */
    public function Buat(string $penyedia, array $pengaturan, array $kredensial): ?GerbangPembayaran
    {
        return match ($penyedia) {
            'Midtrans' => new AdaptorMidtrans($pengaturan, $kredensial),
            'Xendit' => new AdaptorXendit($pengaturan, $kredensial),
            'Tripay' => new AdaptorTripay($pengaturan, $kredensial),
            'Duitku' => new AdaptorDuitku($pengaturan, $kredensial),
            'Ipaymu' => new AdaptorIpaymu($pengaturan, $kredensial),
            'Doku' => new AdaptorDoku($pengaturan, $kredensial),
            default => null,
        };
    }

    /** Gerbang aktif lingkungan ini; null = belum dikonfigurasi/diaktifkan di konsol platform. */
    public function AmbilAktif(): ?GerbangPembayaran
    {
        $konfigurasi = config('integrasi.GerbangPembayaran');

        if (! is_array($konfigurasi) || ! is_string($konfigurasi['Penyedia'] ?? null)) {
            return null;
        }

        /** @var array<string, string|int> $pengaturan */
        $pengaturan = is_array($konfigurasi['Pengaturan'] ?? null) ? $konfigurasi['Pengaturan'] : [];
        /** @var array<string, string> $kredensial */
        $kredensial = is_array($konfigurasi['Kredensial'] ?? null) ? $konfigurasi['Kredensial'] : [];

        return $this->Buat($konfigurasi['Penyedia'], $pengaturan, $kredensial);
    }
}
