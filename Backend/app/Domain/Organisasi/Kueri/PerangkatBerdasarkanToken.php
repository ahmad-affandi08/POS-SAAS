<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Model\Perangkat;

/**
 * Mencari perangkat dari token `{IdTenant}|{rahasia}` (F-02b, §13.4 "device token"). Bagian `IdTenant` hanya
 * menetapkan scope pencarian; perangkat ditemukan hanya bila hash rahasianya cocok di tenant itu, sehingga
 * mengganti `IdTenant` di token tidak membuka tenant lain. Token yang tidak cocok mengosongkan tenant aktif.
 */
final class PerangkatBerdasarkanToken
{
    public function __construct(private readonly KonteksTenant $konteks) {}

    public function Cari(string $token): ?Perangkat
    {
        if (preg_match('/^(\d{1,19})\|([A-Za-z0-9_-]{40,200})$/', $token, $cocok) !== 1) {
            return null;
        }

        $hash = Perangkat::BuatHashToken($cocok[2]);
        $this->konteks->Atur((int) $cocok[1]);
        $perangkat = Perangkat::query()->where('HashToken', $hash)->first();

        if ($perangkat === null || ! is_string($perangkat->HashToken) || ! hash_equals($perangkat->HashToken, $hash)) {
            $this->konteks->Kosongkan();

            return null;
        }

        return $perangkat;
    }
}
