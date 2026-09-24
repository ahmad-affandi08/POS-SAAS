<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Persediaan\Enum\StatusImporStokAwal;
use App\Domain\Persediaan\Impor\Layanan\PengirimTugasImporStokAwal;
use App\Domain\Persediaan\Model\ImporStokAwal;
use Illuminate\Support\Facades\DB;

/**
 * F-05a impor stok awal (DesainF05a C.7): lanjutkan pembuatan draf yang berhenti. Boleh bila impor Gagal saat
 * membuat draf (misal produk diarsipkan setelah pratinjau, atau galat sistem), atau masih Menerapkan tetapi tanpa
 * kemajuan lebih dari 10 menit (worker terhenti). Hanya baris yang belum masuk draf yang diproses (tepat sekali,
 * lewat `ImporStokAwalBaris.IdStokAwal`). Audit `stok-awal.impor.terapkan` (tahap Lanjutkan).
 */
final class LanjutkanImporStokAwal
{
    public const MENIT_TERHENTI = 10;

    public function __construct(private readonly PencatatAudit $audit) {}

    public static function CekBolehLanjutkan(ImporStokAwal $impor): bool
    {
        if ($impor->Status === StatusImporStokAwal::Gagal) {
            return $impor->DiterapkanPada !== null;
        }

        return $impor->Status === StatusImporStokAwal::Menerapkan
            && $impor->DiubahPada !== null
            && $impor->DiubahPada->lt(now()->subMinutes(self::MENIT_TERHENTI));
    }

    public function Jalankan(ImporStokAwal $impor): ImporStokAwal
    {
        $impor = DB::transaction(function () use ($impor): ImporStokAwal {
            $impor = ImporStokAwal::query()->whereKey($impor->Id)->lockForUpdate()->firstOrFail();

            if (! self::CekBolehLanjutkan($impor)) {
                throw new PelanggaranAturanBisnis('StatusImporTidakValid', 'Impor ini tidak bisa dilanjutkan sekarang. Muat ulang halaman untuk melihat statusnya.', 'Impor');
            }

            $statusLama = $impor->Status;

            if ($impor->Status === StatusImporStokAwal::Gagal) {
                $impor->UbahStatus(StatusImporStokAwal::Menerapkan);
            }

            $impor->PesanGalat = null;
            $impor->touch();
            $impor->save();
            $this->audit->Catat('stok-awal.impor.terapkan', $impor, ['Status' => $statusLama->value], ['Tahap' => 'Lanjutkan', 'Status' => $impor->Status->value, 'JumlahDokumen' => $impor->JumlahDokumen]);

            return $impor;
        });

        PengirimTugasImporStokAwal::KirimPenerapan($impor);

        return $impor->refresh();
    }
}
