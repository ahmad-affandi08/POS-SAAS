<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Penjualan\Enum\StatusPenjualan;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PesananOnline;

final class PemeriksaPenyelesaianPesananOnline
{
    public function CekSudahDibayar(PesananOnline $pesanan): bool
    {
        return $pesanan->IdPenjualan !== null
            && Penjualan::query()->whereKey($pesanan->IdPenjualan)->where('Status', StatusPenjualan::Lunas->value)->exists();
    }
}
