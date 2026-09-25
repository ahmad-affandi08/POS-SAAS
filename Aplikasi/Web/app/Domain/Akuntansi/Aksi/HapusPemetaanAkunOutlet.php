<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\PemetaanAkun;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-13a pemetaan akun: hapus override satu outlet sehingga outlet itu kembali memakai pemetaan tingkat tenant untuk
 * jurnal berikutnya. Pemetaan tingkat tenant tidak bisa dihapus (hanya diganti). LogAudit `akun.pemetaan.hapus`.
 */
final class HapusPemetaanAkunOutlet
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis PemetaanTidakAda
     */
    public function Jalankan(PeranAkun $peran, int $idOutlet): void
    {
        DB::transaction(function () use ($peran, $idOutlet): void {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $kunci = [$peran->value, ...array_keys(array_filter(PeranAkun::KUNCI_LAMA, fn (string $baru): bool => $baru === $peran->value))];
            $pemetaan = PemetaanAkun::query()->whereIn('Kunci', $kunci)->where('IdOutlet', $idOutlet)->lockForUpdate()->get();

            if ($pemetaan->isEmpty()) {
                throw new PelanggaranAturanBisnis('PemetaanTidakAda', 'Outlet ini tidak punya pemetaan khusus untuk peran tersebut.');
            }

            foreach ($pemetaan as $satu) {
                $kodeLama = Akun::query()->whereKey($satu->IdAkun)->value('Kode');
                $this->audit->Catat('akun.pemetaan.hapus', $satu, nilaiLama: ['Kunci' => $peran->value, 'IdOutlet' => $idOutlet, 'KodeAkun' => $kodeLama]);
                $satu->delete();
            }
        });
    }
}
