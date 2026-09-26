<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tenant\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\TenantPengguna;
use App\Domain\Tenant\Enum\PenandaTenant;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Support\Collection;

/**
 * Daftar tenant untuk Platform Pengelola (P-07): cari nama/slug/email Owner, saring status langganan & penanda,
 * untuk `TabelData` (D-16). Hanya membaca tabel platform (`Tenant`, `Langganan`) dan keanggotaan (`TenantPengguna`, `Pengguna`) yang
 * bukan `MilikTenant`, jadi tidak membuka data usaha tenant.
 */
final class DaftarTenant
{
    public const SARING_TANPA_PENANDA = 'Tanpa';

    public const KOLOM_URUT = ['DibuatPada', 'Nama'];

    public const KOLOM_SARING = ['Status', 'Penanda'];

    /**
     * Daftar untuk `TabelData` (D-16): cari nama/slug/email Owner; saring status langganan & penanda (pilihan banyak,
     * `Tanpa` = tanpa penanda).
     *
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan): array
    {
        $kata = $permintaan->cari;
        $pola = PenerapKueriTabel::PolaCari($kata);
        $status = $permintaan->AmbilDaftar('Status', array_map(fn (StatusLangganan $s): string => $s->value, StatusLangganan::cases()));
        $penanda = $permintaan->AmbilDaftar('Penanda', [self::SARING_TANPA_PENANDA, ...array_map(fn (PenandaTenant $p): string => $p->value, PenandaTenant::cases())]);
        $penandaNilai = array_values(array_diff($penanda, [self::SARING_TANPA_PENANDA]));
        $tanpaPenanda = in_array(self::SARING_TANPA_PENANDA, $penanda, true);

        $kueri = Tenant::query()
            ->with('Langganan.Paket:Id,Kode,Nama')
            ->when($kata !== '', fn ($kueri) => $kueri->where(fn ($dalam) => $dalam
                ->where('Nama', 'like', $pola)
                ->orWhere('Slug', 'like', $pola)
                ->orWhereIn('Id', TenantPengguna::query()
                    ->select('IdTenant')
                    ->where('Pemilik', true)
                    ->whereIn('IdPengguna', Pengguna::query()->select('Id')->where('Email', 'like', $pola)))))
            ->when($status !== [], fn ($kueri) => $kueri->whereIn('Id', Langganan::query()->select('IdTenant')->whereIn('Status', $status)))
            ->when($penanda !== [], fn ($kueri) => $kueri->where(fn ($dalam) => $dalam
                ->when($penandaNilai !== [], fn ($k) => $k->orWhereIn('Penanda', $penandaNilai))
                ->when($tanpaPenanda, fn ($k) => $k->orWhereNull('Penanda'))));

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, ['DibuatPada' => 'Id', 'Nama' => 'Nama'], function (Collection $tenant): array {
            $email = $this->AmbilEmailPemilik(array_values($tenant->pluck('Id')->all()));

            return array_values($tenant->map(fn (Tenant $t): array => [
                'Uuid' => $t->Uuid,
                'Nama' => $t->Nama,
                'Slug' => $t->Slug,
                'EmailPemilik' => $email[$t->Id] ?? null,
                'KodePaket' => $t->Langganan?->Paket->Kode,
                'StatusLangganan' => $t->Langganan?->Status->value,
                'TrialBerakhirPada' => $t->Langganan?->TrialBerakhirPada?->toIso8601String(),
                'Penanda' => $t->Penanda?->value,
                'DibuatPada' => $t->DibuatPada->toIso8601String(),
            ])->all());
        });
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
            if ($baris->Pengguna->Email !== null) {
                $hasil[$baris->IdTenant] ??= $baris->Pengguna->Email;
            }
        }

        return $hasil;
    }
}
