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
 * - Penukaran (bagian 2, J-16.4) memotong poin yang ditukar kasir sebagai diskon. Penjualan sudah terjadi di kasir,
 *   jadi poin tetap dipotong (saldo boleh minus); masalahnya dikembalikan sebagai alasan tinjauan.
 * - Void membalik seluruh sisa poin bersih penjualan itu dan mengembalikan poin yang ditukar (lot baru berlaku
 *   sampai hari ini + MasaBerlakuBulan).
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

    /**
     * Potong [poin] yang ditukar pada penjualan [idPenjualan]. Hasil: daftar masalah untuk tinjauan (kosong = sesuai):
     * saldo sebelum tukar kurang, loyalti tidak berlaku, poin di bawah minimal, atau nilai diskon ≠ poin × nilai tukar.
     *
     * @return list<string>
     */
    public function CatatPenukaran(int $idPelanggan, int $idPenjualan, int $poin, Uang $nilaiDiskon): array
    {
        $aturan = $this->pengaturan->Ambil();
        $saldo = $this->buku->AmbilSaldo($idPelanggan);
        $masalah = [];

        if (! $aturan->CekBerlaku()) {
            $masalah[] = 'loyalti tidak aktif saat penjualan diterima';
        }

        if ($saldo < $poin) {
            $masalah[] = "saldo {$saldo} poin kurang dari {$poin} poin yang ditukar";
        }

        if ($poin < $aturan->minimalTukarPoin) {
            $masalah[] = "{$poin} poin di bawah minimal tukar {$aturan->minimalTukarPoin} poin";
        }

        $seharusnya = Uang::Dari((string) $aturan->nilaiTukarPoin->multipliedBy($poin)->toScale(2));

        if (! $seharusnya->SamaDengan($nilaiDiskon)) {
            $masalah[] = "nilai diskon {$nilaiDiskon->FormatRupiah()} berbeda dengan {$poin} poin × nilai tukar saat ini ({$seharusnya->FormatRupiah()})";
        }

        $this->buku->Kurangi($idPelanggan, $poin, JenisMutasiPoin::Penukaran, SumberMutasiPoin::Penjualan, $idPenjualan, keterangan: "Diskon {$nilaiDiskon->FormatRupiah()}");

        return $masalah;
    }

    public function BalikVoid(int $idPenjualan): int
    {
        $this->KembalikanPenukaran($idPenjualan);
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

    private function KembalikanPenukaran(int $idPenjualan): void
    {
        $tukar = MutasiPoin::query()
            ->where('Jenis', JenisMutasiPoin::Penukaran->value)
            ->where('JenisSumber', SumberMutasiPoin::Penjualan->value)
            ->where('IdSumber', $idPenjualan)
            ->first();

        if ($tukar === null) {
            return;
        }

        $this->buku->Tambah(
            $tukar->IdPelanggan,
            -$tukar->Poin,
            JenisMutasiPoin::BatalPenukaran,
            SumberMutasiPoin::Penjualan,
            $idPenjualan,
            CarbonImmutable::today()->addMonthsNoOverflow($this->pengaturan->Ambil()->masaBerlakuBulan),
        );
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
