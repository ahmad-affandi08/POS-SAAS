<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Organisasi\Model\Peran;
use App\Domain\Organisasi\Model\PeranIzin;
use App\Domain\Organisasi\Model\TenantPengguna;
use Illuminate\Support\Facades\DB;

/**
 * Membuat/menyelaraskan peran bawaan satu tenant beserta izinnya (PRD §19.1), lalu memberi peran Pemilik kepada
 * pemilik tenant yang belum berperan. Idempoten: dipanggil saat pendaftaran (lewat `SiapkanOrganisasiAwal`) dan
 * oleh perintah `organisasi:siapkan-peran` untuk tenant lama.
 */
final class SiapkanPeranBawaanTenant
{
    public function __construct(private readonly KonteksTenant $konteks) {}

    public function Jalankan(int $idTenant): void
    {
        $tenantSebelumnya = $this->konteks->Ambil();
        $this->konteks->Atur($idTenant);

        try {
            DB::transaction(function () use ($idTenant): void {
                $idPeranPemilik = null;

                foreach (PeranTenantBawaan::cases() as $bawaan) {
                    $peran = Peran::query()->firstOrNew(['Kode' => $bawaan->value]);
                    $peran->fill(['Nama' => $bawaan->AmbilNama(), 'Keterangan' => $bawaan->AmbilKeterangan(), 'Bawaan' => true])->save();
                    $this->SelaraskanIzin($peran, array_map(fn ($izin) => $izin->value, $bawaan->AmbilIzin()));

                    if ($bawaan === PeranTenantBawaan::Pemilik) {
                        $idPeranPemilik = $peran->Id;
                    }
                }

                TenantPengguna::query()
                    ->where('IdTenant', $idTenant)
                    ->where('Pemilik', true)
                    ->whereNull('IdPeran')
                    ->update(['IdPeran' => $idPeranPemilik, 'SemuaOutlet' => true]);
            });
        } finally {
            $tenantSebelumnya === null ? $this->konteks->Kosongkan() : $this->konteks->Atur($tenantSebelumnya);
        }
    }

    /**
     * @param  list<string>  $kunciIzin
     */
    private function SelaraskanIzin(Peran $peran, array $kunciIzin): void
    {
        PeranIzin::query()->where('IdPeran', $peran->Id)->whereNotIn('KunciIzin', $kunciIzin)->delete();
        $ada = PeranIzin::query()->where('IdPeran', $peran->Id)->pluck('KunciIzin')->all();

        foreach (array_diff($kunciIzin, $ada) as $kunci) {
            PeranIzin::query()->create(['IdPeran' => $peran->Id, 'KunciIzin' => $kunci]);
        }
    }
}
