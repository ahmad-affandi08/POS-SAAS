<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Organisasi\Model\Pengguna;
use App\Http\Kontroler\Kontroler;
use Illuminate\Support\Facades\Auth;

/**
 * Bantuan bersama kontroler back-office F-02: pelaku yang masuk, tenant aktif, dan pencarian data lewat ID publik
 * (ULID) di dalam scope tenant. Data tenant lain atau outlet di luar akses pelaku diperlakukan sebagai tidak ada (404).
 */
abstract class DasarKelolaKontroler extends Kontroler
{
    protected function Pelaku(): Pengguna
    {
        $pengguna = Auth::guard('web')->user();
        abort_unless($pengguna instanceof Pengguna, 403);

        return $pengguna;
    }

    protected function IdTenant(): int
    {
        return app(KonteksTenant::class)->Wajib();
    }

    /**
     * Outlet yang boleh diakses pelaku. Null = semua outlet.
     *
     * @return list<int>|null
     */
    protected function IdOutletBoleh(): ?array
    {
        return app(AksesPengguna::class)->AmbilIdOutlet($this->IdTenant(), $this->Pelaku()->Id);
    }

    protected function CariOutlet(string $uuid): Outlet
    {
        $outlet = Outlet::query()->where('Uuid', $uuid)->firstOrFail();
        $boleh = $this->IdOutletBoleh();
        abort_if($boleh !== null && ! in_array($outlet->Id, $boleh, true), 404);

        return $outlet;
    }
}
