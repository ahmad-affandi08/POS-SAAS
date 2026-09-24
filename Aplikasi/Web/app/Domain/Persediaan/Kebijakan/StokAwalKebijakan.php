<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kebijakan;

use App\Domain\Persediaan\Model\StokAwal;

/**
 * Akses dokumen stok awal (DesainF05a C.6.6, D): dokumen dicari lewat `MilikTenant` (tenant lain tidak terlihat),
 * lalu lokasi stoknya (snapshot `StokAwal.IdOutlet`) harus berada di outlet yang boleh diakses pengguna.
 * `idOutletBoleh` null = semua outlet, termasuk lokasi tanpa outlet; akses per outlet tidak mencakup lokasi tanpa
 * outlet. Tidak boleh = 404 di kontroler.
 */
final class StokAwalKebijakan
{
    /**
     * @param  list<int>|null  $idOutletBoleh
     */
    public function CekBolehAkses(StokAwal $stokAwal, ?array $idOutletBoleh): bool
    {
        if ($idOutletBoleh === null) {
            return true;
        }

        return $stokAwal->IdOutlet !== null && in_array($stokAwal->IdOutlet, $idOutletBoleh, true);
    }
}
