<?php

declare(strict_types=1);

namespace App\Http\Perantara\Pengelola;

use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Kueri\SuperAdminAktif;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

/**
 * Perantara Inertia khusus Platform Pengelola: view root & bundle JS terpisah dari tenant (PRD §13.8).
 */
final class BagikanDataInertiaPengelola extends Middleware
{
    protected $rootView = 'Pengelola';

    public function __construct(private readonly SuperAdminAktif $superAdminAktif) {}

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $pengguna = Auth::guard(SesiPengelola::GUARD)->user();

        return [
            ...parent::share($request),
            'NamaAplikasi' => config('app.name'),
            'Lingkungan' => app()->isProduction() ? 'Produksi' : (app()->environment('staging') ? 'Staging' : 'Lokal'),
            'Kilat' => fn () => $request->session()->get('Kilat'),
            'Pengguna' => fn () => $pengguna instanceof PenggunaPengelola ? [
                'Uuid' => $pengguna->Uuid,
                'Nama' => $pengguna->Nama,
                'Email' => $pengguna->Email,
                'KodePeran' => $pengguna->AmbilKodePeran(),
                'Izin' => $pengguna->AmbilDaftarIzin(),
            ] : null,
            // BR-P01.1: peringatan selama Super Admin aktif kurang dari 2.
            'PeringatanSuperAdmin' => fn () => $pengguna instanceof PenggunaPengelola
                && $pengguna->PunyaPeran(PeranPengelolaBawaan::SuperAdmin)
                && $this->superAdminAktif->Hitung() < (int) config('pengelola.MinimalSuperAdminAktif'),
        ];
    }
}
