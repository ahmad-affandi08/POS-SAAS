<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Enum\StatusKeanggotaan;
use App\Domain\Organisasi\Kueri\PemakaianBatasOrganisasi;
use App\Domain\Organisasi\Layanan\PenjagaAnggota;
use App\Domain\Organisasi\Model\TenantPengguna;
use App\Domain\Organisasi\Model\UndanganPengguna;
use App\Domain\Tenant\Layanan\PastikanBatasPaket;
use Illuminate\Support\Facades\DB;

/**
 * Menonaktifkan atau mengaktifkan kembali anggota (F-02). Anggota nonaktif tidak dihapus: riwayat & audit tetap,
 * sesinya langsung terputus karena `IdentifikasiTenantSesi` memeriksa keanggotaan aktif di setiap request.
 * - Pemilik aktif terakhir tidak bisa dinonaktifkan.
 * - Undangan yang dikirim anggota itu dan belum diterima ikut dibatalkan.
 * - Mengaktifkan kembali memakai kursi pengguna lagi (BR-02.1).
 */
final class UbahStatusAnggota
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenjagaAnggota $penjaga,
        private readonly PastikanBatasPaket $batasPaket,
        private readonly PemakaianBatasOrganisasi $pemakaian,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(int $idPelaku, TenantPengguna $anggota, StatusKeanggotaan $tujuan): void
    {
        DB::transaction(function () use ($idPelaku, $anggota, $tujuan): void {
            $idTenant = $this->konteks->Wajib();

            if ($tujuan === StatusKeanggotaan::Aktif && $anggota->Status !== StatusKeanggotaan::Aktif) {
                $this->batasPaket->Pastikan($idTenant, 'BatasPengguna', fn (): int => $this->pemakaian->HitungPengguna($idTenant));
            }

            $anggota = TenantPengguna::query()->lockForUpdate()->findOrFail($anggota->Id);
            $this->penjaga->PastikanBolehMengubah($idPelaku, $anggota);
            $asal = $anggota->Status;

            if (! $asal->BisaBerubahKe($tujuan)) {
                throw new PelanggaranAturanBisnis('StatusTidakBerubah', $tujuan === StatusKeanggotaan::Aktif ? 'Anggota ini sudah aktif.' : 'Anggota ini sudah nonaktif.');
            }

            $nilaiBaru = ['Status' => $tujuan->value];

            if ($tujuan === StatusKeanggotaan::Nonaktif) {
                $this->penjaga->PastikanBukanPemilikTerakhir($anggota);
                $nilaiBaru['UndanganDibatalkan'] = UndanganPengguna::query()
                    ->where('IdTenant', $idTenant)
                    ->where('IdPenggunaPengundang', $anggota->IdPengguna)
                    ->whereNull('DiterimaPada')
                    ->whereNull('DibatalkanPada')
                    ->update(['DibatalkanPada' => now()]);
            }

            $anggota->update([
                'Status' => $tujuan,
                'DinonaktifkanPada' => $tujuan === StatusKeanggotaan::Nonaktif ? now() : null,
            ]);

            $this->audit->Catat(
                $tujuan === StatusKeanggotaan::Nonaktif ? 'pengguna.nonaktifkan' : 'pengguna.aktifkan',
                $anggota,
                nilaiLama: ['Status' => $asal->value],
                nilaiBaru: ['IdPengguna' => $anggota->IdPengguna, ...$nilaiBaru],
            );
        });
    }
}
