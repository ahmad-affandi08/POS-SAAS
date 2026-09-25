<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Penjualan\Layanan\PenjagaPesananTerbuka;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * F-07 mode meja fase 1: kunci bayar online pesanan terbuka agar dua perangkat tidak menagih bill yang sama.
 * Berlaku `MENIT_KUNCI` menit dan diperpanjang oleh perangkat yang sama selama layar Bayar terbuka; perangkat lain
 * mendapat 409 `PesananSedangDibayar` sampai kunci dilepas (bayar, batal, `Lepas`) atau kedaluwarsa.
 */
final class KunciBayarPesananTerbuka
{
    public const MENIT_KUNCI = 2;

    public function __construct(private readonly PenjagaPesananTerbuka $penjaga) {}

    public function Kunci(string $uuid, int $idPerangkat, int $idOutlet): CarbonImmutable
    {
        return DB::transaction(function () use ($uuid, $idPerangkat, $idOutlet): CarbonImmutable {
            $pesanan = $this->penjaga->CariUntukDiubah($uuid, $idOutlet);
            $this->penjaga->PastikanTerbuka($pesanan);

            if ($pesanan->IdPerangkatKunciBayar !== null && $pesanan->IdPerangkatKunciBayar !== $idPerangkat
                && $pesanan->KunciBayarSampai !== null && $pesanan->KunciBayarSampai->isFuture()) {
                throw new PelanggaranAturanBisnis('PesananSedangDibayar', "Pesanan {$pesanan->Nomor} sedang dibayar di perangkat lain. Tunggu sebentar atau minta perangkat itu menutup layar Bayar.", 'Uuid', 409);
            }

            $sampai = CarbonImmutable::now()->addMinutes(self::MENIT_KUNCI);
            $pesanan->update(['IdPerangkatKunciBayar' => $idPerangkat, 'KunciBayarSampai' => $sampai]);

            return $sampai;
        });
    }

    public function Lepas(string $uuid, int $idPerangkat, int $idOutlet): void
    {
        DB::transaction(function () use ($uuid, $idPerangkat, $idOutlet): void {
            $pesanan = $this->penjaga->CariUntukDiubah($uuid, $idOutlet);

            if ($pesanan->IdPerangkatKunciBayar === $idPerangkat) {
                $pesanan->update(['IdPerangkatKunciBayar' => null, 'KunciBayarSampai' => null]);
            }
        });
    }
}
