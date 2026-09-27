<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Kueri;

use App\Domain\Pemenuhan\Model\PengaturanLaundry;

/** Pengaturan laundry tenant aktif (§9.9); tanpa baris = nilai bawaan model. */
final class PengaturanLaundryTenant
{
    public function Ambil(): PengaturanLaundry
    {
        return PengaturanLaundry::query()->first() ?? new PengaturanLaundry;
    }

    /**
     * @return array{Aktif: bool, JamReguler: int, JamExpress: int, Parfum: list<string>, NotifikasiSiap: bool, HariBelumDiambil: int}
     */
    public function AmbilLarik(): array
    {
        $p = $this->Ambil();

        return [
            'Aktif' => $p->Aktif,
            'JamReguler' => $p->JamReguler,
            'JamExpress' => $p->JamExpress,
            'Parfum' => array_values($p->Parfum ?? []),
            'NotifikasiSiap' => $p->NotifikasiSiap,
            'HariBelumDiambil' => $p->HariBelumDiambil,
        ];
    }
}
