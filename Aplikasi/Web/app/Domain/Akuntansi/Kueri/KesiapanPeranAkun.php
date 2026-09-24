<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Layanan\PenentuAkun;

/**
 * Kesiapan pemetaan peran akun yang dibutuhkan sebuah posting (DesainF05a C.5, tipe FE `KesiapanAkun`). Aturan sama
 * dengan `PenentuAkun`: pemetaan outlet (bila `idOutlet` disebut) atau tenant, tipe akun harus cocok. Tanpa outlet =
 * pemetaan tingkat tenant (berlaku untuk semua outlet).
 */
final class KesiapanPeranAkun
{
    public function __construct(private readonly PenentuAkun $penentu) {}

    /**
     * @param  list<PeranAkun>  $peran
     * @return array{Siap: bool, PeranBelumDipetakan: list<array{Kunci: string, Label: string}>}
     */
    public function Periksa(array $peran, ?int $idOutlet = null): array
    {
        $belum = [];
        $sudahDiperiksa = [];

        foreach ($peran as $satu) {
            if (isset($sudahDiperiksa[$satu->value])) {
                continue;
            }

            $sudahDiperiksa[$satu->value] = true;

            if ($this->penentu->CariIdAkun($satu, $idOutlet) === null) {
                $belum[] = ['Kunci' => $satu->value, 'Label' => $satu->AmbilLabel()];
            }
        }

        return ['Siap' => $belum === [], 'PeranBelumDipetakan' => $belum];
    }
}
