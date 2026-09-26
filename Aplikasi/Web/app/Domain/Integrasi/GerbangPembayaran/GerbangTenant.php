<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\GerbangPembayaran;

/**
 * Gerbang pembayaran aktif milik tenant (v2.06) beserta URL webhook tenant itu (dikirim sebagai URL notifikasi
 * per transaksi bagi penyedia yang mendukungnya).
 */
final readonly class GerbangTenant
{
    public function __construct(
        public GerbangPembayaran $gerbang,
        public string $urlNotifikasi,
        public int $idTenant,
    ) {}
}
