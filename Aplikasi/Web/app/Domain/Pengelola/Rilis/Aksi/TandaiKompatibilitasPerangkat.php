<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Rilis\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\StatusKompatibilitas;
use App\Domain\Tenant\Model\KompatibilitasPerangkat;
use Illuminate\Support\Facades\DB;

/**
 * HCL (PRD §17.2.5a, v1.98): tim pengelola menandai model `Tersertifikasi` (sudah diuji di lab) atau `Terbatas`
 * (kendala yang diketahui), atau menghapus tanda sehingga status kembali otomatis. Catatan wajib saat menandai
 * (tampil di halaman publik). Audit `kompatibilitas.tandai`.
 */
final class TandaiKompatibilitasPerangkat
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, KompatibilitasPerangkat $baris, ?StatusKompatibilitas $status, ?string $catatan): KompatibilitasPerangkat
    {
        $catatan = $catatan === null || trim($catatan) === '' ? null : mb_substr(trim($catatan), 0, 500);

        if ($status !== null && ! $status->CekManual()) {
            throw new PelanggaranAturanBisnis('StatusTidakValid', 'Tanda manual hanya Tersertifikasi atau Terbatas.', 'Status');
        }

        if ($status !== null && $catatan === null) {
            throw new PelanggaranAturanBisnis('CatatanWajib', 'Tulis catatan pengujian atau kendalanya.', 'Catatan');
        }

        return DB::transaction(function () use ($pelaku, $baris, $status, $catatan): KompatibilitasPerangkat {
            $terkunci = KompatibilitasPerangkat::query()->lockForUpdate()->findOrFail($baris->Id);
            $lama = ['StatusManual' => $terkunci->StatusManual?->value, 'Catatan' => $terkunci->Catatan];
            $terkunci->fill([
                'StatusManual' => $status,
                'Catatan' => $status === null ? null : $catatan,
                'DiubahOleh' => $pelaku->Id,
            ])->save();
            $this->audit->Catat('kompatibilitas.tandai', $terkunci, nilaiLama: $lama, nilaiBaru: [
                'Jenis' => $terkunci->Jenis->value,
                'Nama' => $terkunci->Nama,
                'StatusManual' => $status?->value,
                'Catatan' => $terkunci->Catatan,
            ], idPelaku: $pelaku->Id);

            return $terkunci;
        });
    }
}
