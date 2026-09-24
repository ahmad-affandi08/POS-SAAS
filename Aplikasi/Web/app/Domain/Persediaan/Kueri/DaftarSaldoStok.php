<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use LogicException;

/**
 * Daftar saldo stok berhalaman + ringkasan (tipe FE `PropsSaldoStok`, DesainF05a C.8).
 *
 * STUB F-05a Tim 0: diimplementasikan Tim F (DesainF05a G). Tanda tangan publik mengikuti DesainF05a C/D;
 * perubahan tanda tangan yang dipakai tim lain diminta lewat lead.
 */
final class DaftarSaldoStok
{
    /**
     * @param  array{Kata: string, UuidGudang: string|null, Keadaan: string, Urut: string}  $saring
     * @param  list<int>|null  $idOutletBoleh
     * @return array<string, mixed>
     */
    public function Ambil(array $saring, ?array $idOutletBoleh, int $halaman): array
    {
        throw new LogicException('F-05a Tim F');
    }
}
