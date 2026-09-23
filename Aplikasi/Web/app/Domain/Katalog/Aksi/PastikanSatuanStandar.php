<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Katalog\Data\DataSatuanStandar;
use App\Domain\Katalog\Model\Satuan;
use Illuminate\Support\Facades\DB;

/**
 * F-01 langkah 4: satuan dasar untuk produk awal. Mengembalikan Id `Satuan` tenant dengan kode standar tertentu,
 * membuatnya bila belum ada (misal tambah produk cepat sebelum template diterapkan: PCS).
 */
final class PastikanSatuanStandar
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(DataSatuanStandar $data): int
    {
        return DB::transaction(function () use ($data): int {
            $ada = Satuan::query()->where('KodeStandar', $data->kode)->first();

            if ($ada !== null) {
                return $ada->Id;
            }

            $satuan = TambahkanSatuanTemplate::Buat($data);
            $this->audit->Catat('satuan.buat', $satuan, nilaiBaru: $satuan->only(['KodeStandar', 'Nama', 'Simbol']));

            return $satuan->Id;
        });
    }
}
