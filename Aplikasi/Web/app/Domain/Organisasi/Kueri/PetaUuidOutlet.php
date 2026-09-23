<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Organisasi\Model\Outlet;

/**
 * Peta `Outlet.Id` → `Outlet.Uuid` tenant aktif untuk domain lain (F-03: `DaftarHarga.IdOutlet` disimpan sebagai Id,
 * dikirim ke POS & `PenentuHarga` sebagai Uuid). Termasuk outlet diarsipkan (daftar harga lama tetap bisa dibaca).
 * Id yang bukan milik tenant aktif tidak muncul di hasil.
 */
final class PetaUuidOutlet
{
    /**
     * @param  list<int>  $idOutlet
     * @return array<int, string>
     */
    public function Ambil(array $idOutlet): array
    {
        if ($idOutlet === []) {
            return [];
        }

        $hasil = [];

        foreach (Outlet::query()->whereIn('Id', array_values(array_unique($idOutlet)))->orderBy('Id')->get(['Id', 'Uuid']) as $outlet) {
            $hasil[$outlet->Id] = $outlet->Uuid;
        }

        return $hasil;
    }
}
