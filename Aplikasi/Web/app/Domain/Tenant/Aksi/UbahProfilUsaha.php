<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Tenant\Data\DataProfilUsahaTenant;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use App\Domain\Tenant\Layanan\PenyimpanLogoTenant;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * F-01 langkah 1: nama usaha, NPWP (angka saja), status PKP, zona waktu (dari kota), dan logo usaha.
 * Logo disimpan di disk privat; path-nya di `Tenant.Pengaturan.PathLogo`. Logo lama dihapus setelah commit, logo
 * baru dihapus bila transaksi gagal. Log audit tidak memuat path berkas.
 */
final class UbahProfilUsaha
{
    private const KOLOM_AUDIT = ['Nama', 'Npwp', 'Pkp', 'ZonaWaktu'];

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PenyimpanLogoTenant $penyimpanLogo,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(DataProfilUsahaTenant $data, ?UploadedFile $logo, bool $hapusLogo): Tenant
    {
        $idTenant = $this->konteks->Wajib();
        $pathBaru = $logo === null ? null : $this->penyimpanLogo->Simpan($idTenant, $logo);

        try {
            return DB::transaction(function () use ($idTenant, $data, $pathBaru, $hapusLogo): Tenant {
                $tenant = $this->penguncian->Kunci($idTenant);
                $lama = $tenant->only(self::KOLOM_AUDIT);
                $npwp = $data->npwp === null ? '' : (string) preg_replace('/\D+/', '', $data->npwp);

                $tenant->fill([
                    'Nama' => trim($data->nama),
                    'Npwp' => $npwp === '' ? null : $npwp,
                    'Pkp' => $data->pkp,
                    'ZonaWaktu' => $data->zonaWaktu ?? $tenant->ZonaWaktu,
                ]);

                $pengaturan = $tenant->Pengaturan ?? [];
                $pathLama = is_string($pengaturan['PathLogo'] ?? null) ? $pengaturan['PathLogo'] : null;
                $perubahanLogo = null;

                if ($pathBaru !== null) {
                    $pengaturan['PathLogo'] = $pathBaru;
                    $perubahanLogo = 'Diganti';
                } elseif ($hapusLogo && $pathLama !== null) {
                    unset($pengaturan['PathLogo']);
                    $perubahanLogo = 'Dihapus';
                }

                if ($perubahanLogo !== null) {
                    $tenant->Pengaturan = $pengaturan;
                }

                $berubah = array_values(array_intersect(array_keys($tenant->getDirty()), self::KOLOM_AUDIT));

                if ($tenant->isDirty()) {
                    $tenant->save();
                }

                if ($berubah !== [] || $perubahanLogo !== null) {
                    $this->audit->Catat(
                        'tenant.profil.ubah',
                        $tenant,
                        nilaiLama: array_intersect_key($lama, array_flip($berubah)),
                        nilaiBaru: [...$tenant->only($berubah), ...($perubahanLogo === null ? [] : ['Logo' => $perubahanLogo])],
                    );
                }

                if ($perubahanLogo !== null && $pathLama !== null) {
                    DB::afterCommit(fn () => $this->penyimpanLogo->Hapus($pathLama));
                }

                return $tenant;
            });
        } catch (Throwable $galat) {
            $this->penyimpanLogo->Hapus($pathBaru);

            throw $galat;
        }
    }
}
