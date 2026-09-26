<?php

declare(strict_types=1);

namespace App\Domain\Promo\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Promo\Enum\StatusKlaimPromo;
use App\Domain\Promo\Model\KlaimPromoPemasok;
use App\Domain\Promo\Model\Promo;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;

/**
 * Layanan publik domain Promo untuk domain Penjualan (F-16c bagian 4b, pendanaan promo): mencatat klaim ke pemasok untuk
 * promo yang ditanggung pemasok, di transaksi DB penerimaan penjualan (idempoten per promo & penjualan), dan
 * membatalkan klaim yang belum diterima saat penjualan di-void. Klaim tidak berjurnal sampai pemasok membayarnya
 * (`TerimaKlaimPemasok`, J-16.5): jumlahnya baru pasti setelah disepakati dengan pemasok.
 */
final class PencatatKlaimPromoPemasok
{
    /**
     * @param  array<string, Uang>  $diskonPerPromo  kunci = Uuid promo, nilai = total potongan promo di penjualan ini
     */
    public function Catat(int $idPenjualan, CarbonImmutable $tanggalBisnis, array $diskonPerPromo): void
    {
        if ($diskonPerPromo === []) {
            return;
        }

        $promo = Promo::query()->whereIn('Uuid', array_keys($diskonPerPromo))->whereNotNull('IdPemasok')->get();

        foreach ($promo as $p) {
            $persen = BigDecimal::of((string) $p->PersenDanaPemasok);
            $diskon = $diskonPerPromo[$p->Uuid];

            if ($p->IdPemasok === null || ! $persen->isPositive() || $diskon->Bandingkan(Uang::Nol()) <= 0) {
                continue;
            }

            if (KlaimPromoPemasok::query()->where('IdPromo', $p->Id)->where('IdPenjualan', $idPenjualan)->exists()) {
                continue;
            }

            KlaimPromoPemasok::query()->create([
                'IdPromo' => $p->Id,
                'IdPemasok' => $p->IdPemasok,
                'IdPenjualan' => $idPenjualan,
                'TanggalBisnis' => $tanggalBisnis->toDateString(),
                'JumlahDiskon' => $diskon->KeString(),
                'PersenDana' => (string) $persen->toScale(2),
                'Jumlah' => $diskon->Kali($persen->withPointMovedLeft(2))->KeString(),
                'Status' => StatusKlaimPromo::Terbuka,
            ]);
        }
    }

    /** Void penjualan: klaim yang belum diterima dibatalkan (yang sudah diterima tetap, dikoreksi manual). */
    public function Batalkan(int $idPenjualan): void
    {
        KlaimPromoPemasok::query()
            ->where('IdPenjualan', $idPenjualan)
            ->where('Status', StatusKlaimPromo::Terbuka->value)
            ->update(['Status' => StatusKlaimPromo::Dibatalkan->value, 'DiubahPada' => now()]);
    }
}
