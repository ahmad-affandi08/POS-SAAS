<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Model\StasiunDapur;
use Illuminate\Support\Facades\DB;

/**
 * F-10a: mengarsipkan atau memulihkan stasiun dapur. Kategori yang merujuk stasiun diarsipkan tetap menyimpan
 * rujukannya, tetapi itemnya dirutekan ke stasiun bawaan (stasiun aktif pertama) sampai kategori diubah.
 */
final class UbahStatusStasiunDapur
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(StasiunDapur $stasiun, StatusOrganisasi $tujuan): StasiunDapur
    {
        return DB::transaction(function () use ($stasiun, $tujuan): StasiunDapur {
            $stasiun = StasiunDapur::query()->lockForUpdate()->findOrFail($stasiun->Id);
            $asal = $stasiun->Status;

            if (! $asal->BisaBerubahKe($tujuan)) {
                throw new PelanggaranAturanBisnis('StatusTidakBerubah', "Stasiun {$stasiun->Nama} sudah berstatus {$tujuan->AmbilLabel()}.");
            }

            if ($tujuan === StatusOrganisasi::Aktif && StasiunDapur::query()->where('Status', 'Aktif')->count() >= SimpanStasiunDapur::MAKS_AKTIF) {
                throw new PelanggaranAturanBisnis('BatasStasiun', 'Maksimal '.SimpanStasiunDapur::MAKS_AKTIF.' stasiun dapur aktif.');
            }

            $stasiun->update(['Status' => $tujuan, 'DiarsipkanPada' => $tujuan === StatusOrganisasi::Diarsipkan ? now() : null]);
            $this->audit->Catat($tujuan === StatusOrganisasi::Diarsipkan ? 'stasiun-dapur.arsipkan' : 'stasiun-dapur.pulihkan', $stasiun, nilaiLama: ['Status' => $asal->value], nilaiBaru: ['Status' => $tujuan->value]);

            return $stasiun;
        });
    }
}
