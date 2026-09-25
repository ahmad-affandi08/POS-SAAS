<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pelanggan\Model\PengaturanLoyalti;
use Illuminate\Support\Facades\DB;

/**
 * Simpan pengaturan loyalti tenant (F-16b, izin `pelanggan.kelola`): aktif, Rp belanja per poin (≥ Rp 100), masa
 * berlaku poin 1–60 bulan, periode evaluasi tier 1–24 bulan. Perubahan hanya berlaku untuk perolehan berikutnya.
 * Audit `loyalti.pengaturan`.
 */
final class SimpanPengaturanLoyalti
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(bool $aktif, Uang $belanjaPerPoin, int $masaBerlakuBulan, int $bulanEvaluasiTier, int $idPengguna): PengaturanLoyalti
    {
        if ($belanjaPerPoin->Bandingkan(Uang::Dari(100)) < 0) {
            throw new PelanggaranAturanBisnis('BelanjaPerPoinTidakValid', 'Belanja per poin minimal Rp 100.', 'BelanjaPerPoin');
        }

        if ($masaBerlakuBulan < 1 || $masaBerlakuBulan > 60) {
            throw new PelanggaranAturanBisnis('MasaBerlakuTidakValid', 'Masa berlaku poin 1–60 bulan.', 'MasaBerlakuBulan');
        }

        if ($bulanEvaluasiTier < 1 || $bulanEvaluasiTier > 24) {
            throw new PelanggaranAturanBisnis('PeriodeTierTidakValid', 'Periode evaluasi tier 1–24 bulan.', 'BulanEvaluasiTier');
        }

        return DB::transaction(function () use ($aktif, $belanjaPerPoin, $masaBerlakuBulan, $bulanEvaluasiTier, $idPengguna): PengaturanLoyalti {
            $p = PengaturanLoyalti::query()->lockForUpdate()->first() ?? new PengaturanLoyalti;
            $lama = ['Aktif' => $p->Aktif, 'BelanjaPerPoin' => Uang::Dari($p->BelanjaPerPoin)->KeString(), 'MasaBerlakuBulan' => $p->MasaBerlakuBulan, 'BulanEvaluasiTier' => $p->BulanEvaluasiTier];
            $baru = ['Aktif' => $aktif, 'BelanjaPerPoin' => $belanjaPerPoin->KeString(), 'MasaBerlakuBulan' => $masaBerlakuBulan, 'BulanEvaluasiTier' => $bulanEvaluasiTier];
            $p->fill($baru);
            $p->save();

            if ($lama !== $baru) {
                $this->audit->Catat('loyalti.pengaturan', $p, $lama, $baru, idPengguna: $idPengguna);
            }

            return $p;
        });
    }
}
