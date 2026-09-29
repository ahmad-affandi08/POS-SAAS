<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Tugas;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Aksi\BuatNotifikasiPengguna;
use App\Domain\Organisasi\Aksi\PutuskanPersetujuanJarakJauh;
use App\Domain\Organisasi\Enum\JenisNotifikasiPengguna;
use App\Domain\Organisasi\Enum\StatusKeanggotaan;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\PermintaanPersetujuan;
use App\Domain\Organisasi\Model\TenantPengguna;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Membuat notifikasi untuk semua anggota yang berwenang memutuskan permintaan. */
final class BuatNotifikasiPersetujuanTugas implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $idTenant, public int $idPermintaan) {}

    public function handle(KonteksTenant $konteks, AnggotaOutlet $anggota, BuatNotifikasiPengguna $buat): void
    {
        $sebelumnya = $konteks->Ambil();
        $konteks->Atur($this->idTenant);

        try {
            $permintaan = PermintaanPersetujuan::query()->whereKey($this->idPermintaan)->first();

            if ($permintaan === null || $permintaan->CekLewatWaktu()) {
                return;
            }

            $pemohon = (string) Pengguna::query()->whereKey($permintaan->IdPemohon)->value('Nama');
            $calon = TenantPengguna::query()
                ->where('IdTenant', $this->idTenant)
                ->where('Status', StatusKeanggotaan::Aktif->value)
                ->where('IdPengguna', '!=', $permintaan->IdPemohon)
                ->with('Pengguna:Id,Uuid')
                ->get();

            foreach ($calon as $keanggotaan) {
                $pengguna = $keanggotaan->Pengguna;
                $dataAnggota = $anggota->Cari($this->idTenant, $pengguna->Uuid, $permintaan->IdOutlet);

                if ($dataAnggota === null || ! PutuskanPersetujuanJarakJauh::CekBolehMemutuskan($permintaan, $dataAnggota)) {
                    continue;
                }

                $buat->Jalankan(
                    $pengguna->Id,
                    JenisNotifikasiPengguna::Persetujuan,
                    'Persetujuan:'.$permintaan->Uuid,
                    $permintaan->Judul,
                    'Permintaan dari '.($pemohon === '' ? 'kasir' : $pemohon).', berlaku 10 menit.',
                    ['Tautan' => 'persetujuan', 'UuidPersetujuan' => $permintaan->Uuid],
                );
            }
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }
    }
}
