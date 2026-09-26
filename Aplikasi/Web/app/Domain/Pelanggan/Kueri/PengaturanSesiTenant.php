<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;

/** Paket sesi tenant aktif (F-16d bagian 2): berlaku bila paket langganan punya fitur `pelanggan.paket-sesi` (Pro ke atas). */
final class PengaturanSesiTenant
{
    public const KUNCI_FITUR = 'pelanggan.paket-sesi';

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PemeriksaFiturTenant $fitur,
    ) {}

    public function CekBerlaku(): bool
    {
        return $this->fitur->CekAktif($this->konteks->Wajib(), self::KUNCI_FITUR);
    }
}
