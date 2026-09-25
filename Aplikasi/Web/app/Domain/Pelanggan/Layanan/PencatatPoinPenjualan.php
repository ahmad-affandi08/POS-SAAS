<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Layanan;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pelanggan\Enum\JenisMutasiPoin;
use App\Domain\Pelanggan\Enum\SumberMutasiPoin;
use App\Domain\Pelanggan\Kueri\PengaturanLoyaltiTenant;
use App\Domain\Pelanggan\Model\MutasiPoin;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\TierPelanggan;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * Layanan publik domain Pelanggan untuk domain Penjualan (F-16b): poin dicatat di transaksi DB yang sama dengan
 * penerimaan penjualan, void, dan retur (sinkron, idempoten per dokumen).
 * - Perolehan = ⌊TotalAkhir ÷ BelanjaPerPoin × PengaliPoin tier⌋, berlaku sampai tanggal bisnis + MasaBerlakuBulan.
 *   Hanya bila loyalti berlaku (diaktifkan tenant & fitur paket `pelanggan.loyalti`).
 * - Void membalik seluruh sisa poin bersih penjualan itu.
 * - Retur membalik secara proporsional: poin bersih sesudah retur = ⌊Perolehan × (TotalAkhir − Σ refund) ÷
 *   TotalAkhir⌋. Pembalikan tetap berjalan walau loyalti sudah dinonaktifkan (perolehan lama dikoreksi).
 */
final class PencatatPoinPenjualan
{
    public function __construct(
        private readonly PengaturanLoyaltiTenant $pengaturan,
        private readonly BukuPoin $buku,
    ) {}

    /** Hasil: poin yang diperoleh (0 bila loyalti tidak berlaku atau belanja di bawah satu poin). */
    public function CatatPerolehan(int $idPelanggan, int $idPenjualan, Uang $totalAkhir, CarbonImmutable $tanggalBisnis): int
    {
        $aturan = $this->pengaturan->Ambil();

        if (! $aturan->CekBerlaku() || $aturan->belanjaPerPoin->isLessThanOrEqualTo(0) || $totalAkhir->BernilaiNegatif()) {
            return 0;
        }

        $pelanggan = Pelanggan::query()->whereKey($idPelanggan)->first(['Id', 'IdTier']);
        $pengali = $pelanggan?->IdTier === null ? '1' : (TierPelanggan::query()->whereKey($pelanggan->IdTier)->value('PengaliPoin') ?? '1');
        $poin = BigDecimal::of($totalAkhir->KeString())
            ->multipliedBy(BigDecimal::of((string) $pengali))
            ->dividedBy($aturan->belanjaPerPoin, 0, RoundingMode::Down)
            ->toInt();

        if ($poin <= 0) {
            return 0;
        }

        $this->buku->Tambah(
            $idPelanggan,
            $poin,
            JenisMutasiPoin::Perolehan,
            SumberMutasiPoin::Penjualan,
            $idPenjualan,
            $tanggalBisnis->addMonthsNoOverflow($aturan->masaBerlakuBulan),
        );

        return $poin;
    }

    public function BalikVoid(int $idPenjualan): int
    {
        $perolehan = $this->CariPerolehan($idPenjualan);

        if ($perolehan === null) {
            return 0;
        }

        $bersih = $perolehan->Poin + $this->AmbilPembalikanRetur($idPenjualan);

        if ($bersih <= 0) {
            return 0;
        }

        $this->buku->Kurangi($perolehan->IdPelanggan, $bersih, JenisMutasiPoin::PembalikanVoid, SumberMutasiPoin::Penjualan, $idPenjualan, $idPenjualan);

        return $bersih;
    }

    public function BalikRetur(int $idPenjualan, int $idRetur, Uang $totalAkhirPenjualan, Uang $totalReturKumulatif): int
    {
        $perolehan = $this->CariPerolehan($idPenjualan);

        if ($perolehan === null || $totalAkhirPenjualan->BernilaiNol()) {
            return 0;
        }

        $sisaBelanja = $totalAkhirPenjualan->Kurangi($totalReturKumulatif);
        $target = $sisaBelanja->BernilaiNegatif() ? 0 : BigDecimal::of((string) $perolehan->Poin)
            ->multipliedBy(BigDecimal::of($sisaBelanja->KeString()))
            ->dividedBy(BigDecimal::of($totalAkhirPenjualan->KeString()), 0, RoundingMode::Down)
            ->toInt();
        $bersih = $perolehan->Poin + $this->AmbilPembalikanRetur($idPenjualan);
        $balik = $bersih - $target;

        if ($balik <= 0) {
            return 0;
        }

        $this->buku->Kurangi($perolehan->IdPelanggan, $balik, JenisMutasiPoin::PembalikanRetur, SumberMutasiPoin::ReturPenjualan, $idRetur, $idPenjualan);

        return $balik;
    }

    private function CariPerolehan(int $idPenjualan): ?MutasiPoin
    {
        return MutasiPoin::query()
            ->where('Jenis', JenisMutasiPoin::Perolehan->value)
            ->where('JenisSumber', SumberMutasiPoin::Penjualan->value)
            ->where('IdSumber', $idPenjualan)
            ->first();
    }

    private function AmbilPembalikanRetur(int $idPenjualan): int
    {
        return (int) MutasiPoin::query()
            ->where('Jenis', JenisMutasiPoin::PembalikanRetur->value)
            ->where('IdSumberAsal', $idPenjualan)
            ->sum('Poin');
    }
}
