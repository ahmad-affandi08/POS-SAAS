<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Operasional\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Operasional\Data\DataCatatanBackup;
use App\Domain\Pengelola\Operasional\Enum\SumberCatatanBackup;
use App\Domain\Pengelola\Operasional\Model\CatatanBackup;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Support\Facades\DB;

/**
 * Mencatat hasil backup atau uji restore (P-11, §14.5): dari skrip backup server lewat `pengelola:catat-backup`
 * (tanpa pelaku) atau isian manual Teknis. Lokasi hanya path/nama objek, tidak boleh memuat kredensial.
 */
final class CatatHasilBackup
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(DataCatatanBackup $data, ?PenggunaPengelola $pelaku = null): CatatanBackup
    {
        if ($data->selesaiPada->isFuture()) {
            throw new PelanggaranAturanBisnis('P-11', 'Waktu selesai tidak boleh di masa depan.', 'SelesaiPada');
        }

        if ($data->lokasi !== null && preg_match('#://[^/\s]*:[^/\s]*@#', $data->lokasi) === 1) {
            throw new PelanggaranAturanBisnis('P-11', 'Lokasi backup tidak boleh memuat kredensial. Tulis path atau nama objeknya saja.', 'Lokasi');
        }

        return DB::transaction(function () use ($data, $pelaku): CatatanBackup {
            $catatan = CatatanBackup::query()->create([
                'Jenis' => $data->jenis,
                'Hasil' => $data->hasil,
                'SelesaiPada' => $data->selesaiPada,
                'UkuranByte' => $data->ukuranByte,
                'Lokasi' => $data->lokasi,
                'Keterangan' => $data->keterangan,
                'Sumber' => $pelaku === null ? SumberCatatanBackup::Skrip : SumberCatatanBackup::Manual,
                'IdPenggunaPengelola' => $pelaku?->Id,
            ]);

            $this->audit->Catat(
                'operasional.backup.catat',
                $catatan,
                nilaiBaru: [
                    'Jenis' => $data->jenis->value,
                    'Hasil' => $data->hasil->value,
                    'SelesaiPada' => $data->selesaiPada->toIso8601String(),
                    'Sumber' => $catatan->Sumber->value,
                ],
                idPelaku: $pelaku?->Id,
            );

            return $catatan;
        });
    }
}
