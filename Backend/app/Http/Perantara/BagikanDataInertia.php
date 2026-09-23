<?php

declare(strict_types=1);

namespace App\Http\Perantara;

use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Tenant\Kueri\RingkasanTenant;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Perantara Inertia: menentukan view root dan data yang dibagikan ke semua halaman (PRD §13.5).
 */
final class BagikanDataInertia extends Middleware
{
    protected $rootView = 'Aplikasi';

    public function __construct(private readonly RingkasanTenant $ringkasanTenant) {}

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $pengguna = $request->user('web');
        $idTenant = $request->session()->get(IdentifikasiTenantSesi::KUNCI_SESI);

        return [
            ...parent::share($request),
            'NamaAplikasi' => config('app.name'),
            'Kilat' => fn () => $request->session()->get('Kilat'),
            'Pengguna' => fn () => $pengguna instanceof Pengguna ? [
                'Uuid' => $pengguna->Uuid,
                'Nama' => $pengguna->Nama,
                'Email' => $pengguna->Email,
                // BR-00.5: banner pengingat selama email belum terverifikasi.
                'EmailTerverifikasi' => $pengguna->EmailDiverifikasiPada !== null,
            ] : null,
            // Hanya nama tenant yang sudah dipilih; keanggotaan diperiksa IdentifikasiTenantSesi.
            'TenantAktif' => function () use ($pengguna, $idTenant): ?array {
                $tenant = $pengguna instanceof Pengguna && is_int($idTenant) ? ($this->ringkasanTenant->Ambil([$idTenant])[0] ?? null) : null;

                return $tenant === null ? null : ['Nama' => $tenant['Nama']];
            },
        ];
    }
}
