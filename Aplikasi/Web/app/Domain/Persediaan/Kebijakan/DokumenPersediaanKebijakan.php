<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kebijakan;

use App\Domain\Persediaan\Model\TransferStok;

/**
 * Akses dokumen persediaan F-05b per outlet (pola `StokAwalKebijakan`): `idOutletBoleh` null = semua outlet
 * (termasuk lokasi tanpa outlet); akses per outlet tidak mencakup lokasi tanpa outlet. Transfer terlihat bila outlet
 * asal atau tujuan boleh diakses; mengirim/mengubah butuh outlet asal, menerima/menutup butuh outlet tujuan.
 * Tidak boleh = 404 di kontroler.
 */
final class DokumenPersediaanKebijakan
{
    /**
     * @param  list<int>|null  $idOutletBoleh
     */
    public function CekBolehOutlet(?int $idOutlet, ?array $idOutletBoleh): bool
    {
        return $idOutletBoleh === null || ($idOutlet !== null && in_array($idOutlet, $idOutletBoleh, true));
    }

    /**
     * @param  list<int>|null  $idOutletBoleh
     */
    public function CekBolehLihatTransfer(TransferStok $transfer, ?array $idOutletBoleh): bool
    {
        return $this->CekBolehOutlet($transfer->IdOutletAsal, $idOutletBoleh) || $this->CekBolehOutlet($transfer->IdOutletTujuan, $idOutletBoleh);
    }
}
