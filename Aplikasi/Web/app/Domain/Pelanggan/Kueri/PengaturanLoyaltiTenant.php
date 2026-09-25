<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Pelanggan\Data\DataPengaturanLoyalti;
use App\Domain\Pelanggan\Model\PengaturanLoyalti;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;
use Brick\Math\BigDecimal;

/** Pengaturan loyalti tenant aktif (F-16b); tanpa baris = nilai bawaan model (nonaktif). */
final class PengaturanLoyaltiTenant
{
    public const KUNCI_FITUR = 'pelanggan.loyalti';

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PemeriksaFiturTenant $fitur,
    ) {}

    public function Ambil(): DataPengaturanLoyalti
    {
        $p = PengaturanLoyalti::query()->first() ?? new PengaturanLoyalti;

        return new DataPengaturanLoyalti(
            aktif: $p->Aktif,
            fiturAktif: $this->fitur->CekAktif($this->konteks->Wajib(), self::KUNCI_FITUR),
            belanjaPerPoin: BigDecimal::of($p->BelanjaPerPoin),
            masaBerlakuBulan: $p->MasaBerlakuBulan,
            bulanEvaluasiTier: $p->BulanEvaluasiTier,
        );
    }
}
