<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;

/**
 * F-10a: menambah area/meja baru hanya di outlet aktif yang fitur paket `pos.mode-meja`-nya aktif (paket, add-on,
 * override, dan modul template outlet; BR-P04.7, BR-01.3). Mengubah atau mengarsipkan data lama tetap boleh saat
 * fitur mati (misal setelah turun paket) agar data tidak terkunci.
 */
final class PenjagaModeMeja
{
    public function __construct(private readonly PemeriksaFiturTenant $fitur) {}

    public function CekAktif(Outlet $outlet): bool
    {
        return $this->fitur->CekAktifDiOutlet($outlet->IdTenant, $outlet->Id, PemeriksaFiturTenant::KUNCI_MODE_MEJA);
    }

    public function PastikanBisaMenambah(Outlet $outlet): void
    {
        if ($outlet->Status !== StatusOrganisasi::Aktif) {
            throw new PelanggaranAturanBisnis('OutletDiarsipkan', 'Outlet ini diarsipkan. Pulihkan outlet dulu untuk menambah meja.');
        }

        if (! $this->CekAktif($outlet)) {
            throw new PelanggaranAturanBisnis('FiturTidakAktif', 'Mode meja belum aktif untuk outlet ini. Naikkan paket ke Pro atau aktifkan modul meja di template outlet.');
        }
    }
}
