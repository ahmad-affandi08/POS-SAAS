<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tenant\Kueri;

use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\TenantPengguna;
use App\Domain\Tenant\Enum\PenandaTenant;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Daftar tenant untuk Platform Pengelola (P-07): cari nama/slug/email Owner, saring status langganan & penanda,
 * berhalaman. Hanya membaca tabel platform (`Tenant`, `Langganan`) dan keanggotaan (`TenantPengguna`, `Pengguna`) yang
 * bukan `MilikTenant`, jadi tidak membuka data usaha tenant.
 */
final class DaftarTenant
{
    public const PER_HALAMAN = 25;

    public const SARING_TANPA_PENANDA = 'Tanpa';

    /**
     * @param  PenandaTenant|self::SARING_TANPA_PENANDA|null  $penanda
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function Cari(string $kata, ?StatusLangganan $status, PenandaTenant|string|null $penanda): LengthAwarePaginator
    {
        $pola = '%'.addcslashes($kata, '%_\\').'%';

        return Tenant::query()
            ->with('Langganan.Paket:Id,Kode,Nama')
            ->when($kata !== '', fn ($kueri) => $kueri->where(fn ($dalam) => $dalam
                ->where('Nama', 'like', $pola)
                ->orWhere('Slug', 'like', $pola)
                ->orWhereIn('Id', TenantPengguna::query()
                    ->select('IdTenant')
                    ->where('Pemilik', true)
                    ->whereIn('IdPengguna', Pengguna::query()->select('Id')->where('Email', 'like', $pola)))))
            ->when($status !== null, fn ($kueri) => $kueri->whereIn(
                'Id',
                Langganan::query()->select('IdTenant')->where('Status', $status?->value),
            ))
            ->when($penanda === self::SARING_TANPA_PENANDA, fn ($kueri) => $kueri->whereNull('Penanda'))
            ->when($penanda instanceof PenandaTenant, fn ($kueri) => $kueri->where('Penanda', $penanda instanceof PenandaTenant ? $penanda->value : null))
            ->orderByDesc('Id')
            ->paginate(self::PER_HALAMAN, ['*'], 'halaman')
            ->withQueryString();
    }

    /**
     * Email Owner (pemilik pertama) per tenant untuk kolom daftar, tanpa N+1.
     *
     * @param  list<int>  $idTenant
     * @return array<int, string>
     */
    public function AmbilEmailPemilik(array $idTenant): array
    {
        if ($idTenant === []) {
            return [];
        }

        $hasil = [];
        $anggota = TenantPengguna::query()
            ->with('Pengguna:Id,Email')
            ->whereIn('IdTenant', $idTenant)
            ->where('Pemilik', true)
            ->orderBy('Id')
            ->get();

        foreach ($anggota as $baris) {
            $hasil[$baris->IdTenant] ??= $baris->Pengguna->Email;
        }

        return $hasil;
    }
}
