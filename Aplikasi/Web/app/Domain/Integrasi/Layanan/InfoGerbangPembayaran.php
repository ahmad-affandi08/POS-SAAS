<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Layanan;

use App\Domain\Integrasi\GerbangPembayaran\PembuatGerbangPembayaran;

/**
 * Ringkasan gerbang pembayaran aktif untuk back-office tenant (F-08 QRIS dinamis): hanya ada/tidaknya gerbang dan
 * label penyedianya. Kredensial & pengaturan tidak pernah keluar dari sini.
 */
final class InfoGerbangPembayaran
{
    private const LABEL = [
        'Midtrans' => 'Midtrans',
        'Xendit' => 'Xendit',
        'Tripay' => 'Tripay',
        'Duitku' => 'Duitku',
        'Ipaymu' => 'iPaymu',
        'Doku' => 'DOKU',
    ];

    public function __construct(private readonly PembuatGerbangPembayaran $pembuat) {}

    /**
     * @return array{Aktif: bool, Penyedia: string|null}
     */
    public function Ambil(): array
    {
        $gerbang = $this->pembuat->AmbilAktif();

        return $gerbang === null
            ? ['Aktif' => false, 'Penyedia' => null]
            : ['Aktif' => true, 'Penyedia' => self::LABEL[$gerbang->AmbilKode()] ?? $gerbang->AmbilKode()];
    }
}
