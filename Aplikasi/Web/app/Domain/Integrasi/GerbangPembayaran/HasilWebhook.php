<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\GerbangPembayaran;

/**
 * Notifikasi gerbang yang tanda tangannya sah. [jumlah] string desimal bila penyedia mengirimkannya.
 */
final readonly class HasilWebhook
{
    public function __construct(
        public string $nomorPesanan,
        public StatusPembayaranGerbang $status,
        public ?string $jumlah = null,
        public ?string $idReferensi = null,
    ) {}
}
