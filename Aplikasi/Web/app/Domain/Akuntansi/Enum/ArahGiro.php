<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Enum;

/** v3.42: giro dari pelanggan (Masuk) atau ke pemasok (Keluar). */
enum ArahGiro: string
{
    case Masuk = 'Masuk';
    case Keluar = 'Keluar';

    public function AmbilLabel(): string
    {
        return $this === self::Masuk ? 'Giro masuk (dari pelanggan)' : 'Giro keluar (ke pemasok)';
    }

    /** Peran akun penampung selama giro belum cair. */
    public function AmbilPeranPenampung(): PeranAkun
    {
        return $this === self::Masuk ? PeranAkun::GiroDiterima : PeranAkun::HutangGiro;
    }
}
