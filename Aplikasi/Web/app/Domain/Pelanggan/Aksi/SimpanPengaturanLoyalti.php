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
 * Bagian 2: nilai tukar per poin Rp 1 s.d. belanja per poin (potongan tidak melebihi belanja yang menghasilkan poin) dan
 * minimal poin sekali tukar 1–100.000; `null` = nilai tersimpan tidak diubah. Audit `loyalti.pengaturan`.
 */
final class SimpanPengaturanLoyalti
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(
        bool $aktif,
        Uang $belanjaPerPoin,
        int $masaBerlakuBulan,
        int $bulanEvaluasiTier,
        int $idPengguna,
        ?Uang $nilaiTukarPoin = null,
        ?int $minimalTukarPoin = null,
    ): PengaturanLoyalti {
        if ($belanjaPerPoin->Bandingkan(Uang::Dari(100)) < 0) {
            throw new PelanggaranAturanBisnis('BelanjaPerPoinTidakValid', 'Belanja per poin minimal Rp 100.', 'BelanjaPerPoin');
        }

        if ($masaBerlakuBulan < 1 || $masaBerlakuBulan > 60) {
            throw new PelanggaranAturanBisnis('MasaBerlakuTidakValid', 'Masa berlaku poin 1–60 bulan.', 'MasaBerlakuBulan');
        }

        if ($bulanEvaluasiTier < 1 || $bulanEvaluasiTier > 24) {
            throw new PelanggaranAturanBisnis('PeriodeTierTidakValid', 'Periode evaluasi tier 1–24 bulan.', 'BulanEvaluasiTier');
        }

        if ($minimalTukarPoin !== null && ($minimalTukarPoin < 1 || $minimalTukarPoin > 100000)) {
            throw new PelanggaranAturanBisnis('MinimalTukarTidakValid', 'Minimal tukar 1–100.000 poin.', 'MinimalTukarPoin');
        }

        return DB::transaction(function () use ($aktif, $belanjaPerPoin, $masaBerlakuBulan, $bulanEvaluasiTier, $idPengguna, $nilaiTukarPoin, $minimalTukarPoin): PengaturanLoyalti {
            $p = PengaturanLoyalti::query()->lockForUpdate()->first() ?? new PengaturanLoyalti;
            $nilaiTukar = $nilaiTukarPoin ?? Uang::Dari($p->NilaiTukarPoin);

            if ($nilaiTukar->Bandingkan(Uang::Dari(1)) < 0 || $nilaiTukar->Bandingkan($belanjaPerPoin) > 0) {
                throw new PelanggaranAturanBisnis('NilaiTukarTidakValid', 'Nilai tukar per poin minimal Rp 1 dan tidak boleh melebihi belanja per poin.', 'NilaiTukarPoin');
            }

            $lama = [
                'Aktif' => $p->Aktif,
                'BelanjaPerPoin' => Uang::Dari($p->BelanjaPerPoin)->KeString(),
                'MasaBerlakuBulan' => $p->MasaBerlakuBulan,
                'BulanEvaluasiTier' => $p->BulanEvaluasiTier,
                'NilaiTukarPoin' => Uang::Dari($p->NilaiTukarPoin)->KeString(),
                'MinimalTukarPoin' => $p->MinimalTukarPoin,
            ];
            $baru = [
                'Aktif' => $aktif,
                'BelanjaPerPoin' => $belanjaPerPoin->KeString(),
                'MasaBerlakuBulan' => $masaBerlakuBulan,
                'BulanEvaluasiTier' => $bulanEvaluasiTier,
                'NilaiTukarPoin' => $nilaiTukar->KeString(),
                'MinimalTukarPoin' => $minimalTukarPoin ?? $p->MinimalTukarPoin,
            ];
            $p->fill($baru);
            $p->save();

            if ($lama !== $baru) {
                $this->audit->Catat('loyalti.pengaturan', $p, $lama, $baru, idPengguna: $idPengguna);
            }

            return $p;
        });
    }
}
