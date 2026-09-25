<?php

declare(strict_types=1);

namespace App\Domain\Promo\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Promo\Enum\StatusPemakaianVoucher;
use App\Domain\Promo\Enum\StatusPromo;
use App\Domain\Promo\Enum\StatusVoucher;
use App\Domain\Promo\Kueri\PromoBerlaku;
use App\Domain\Promo\Model\Promo;
use App\Domain\Promo\Model\Voucher;
use App\Domain\Promo\Model\VoucherPemakaian;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Kasir memasukkan kode voucher (F-16c bagian 2, wajib online §18.4): voucher diperiksa lalu dipesan untuk penjualan
 * perangkat [uuidPenjualan] selama `MENIT_PESAN` menit agar tidak dipakai perangkat lain. Baris voucher dikunci
 * `FOR UPDATE`; sisa = `MaksimalPakai` − `JumlahDipakai` − pesanan lain yang masih berlaku. Memesan ulang untuk penjualan
 * yang sama memperpanjang pesanan (idempoten). Hasil berisi promo voucher (bentuk sama dengan `GET /promo`).
 */
final class PesanVoucherPos
{
    public const MENIT_PESAN = 60;

    public function __construct(private readonly PromoBerlaku $berlaku) {}

    /**
     * @return array{Voucher: array{Kode: string, UuidPromo: string, DipesanSampai: string, SisaPakai: int|null}, Promo: array<string, mixed>}
     */
    public function Jalankan(string $kode, string $uuidPenjualan, int $idPerangkat): array
    {
        if (! $this->berlaku->CekFiturAktif()) {
            throw new PelanggaranAturanBisnis('VoucherTidakDitemukan', 'Kode voucher tidak ditemukan.', 'Kode', 404);
        }

        return DB::transaction(function () use ($kode, $uuidPenjualan, $idPerangkat): array {
            $sekarang = CarbonImmutable::now();
            $voucher = Voucher::query()->where('Kode', Voucher::RapikanKode($kode))->lockForUpdate()->first()
                ?? throw new PelanggaranAturanBisnis('VoucherTidakDitemukan', 'Kode voucher tidak ditemukan.', 'Kode', 404);
            $promo = Promo::query()->findOrFail($voucher->IdPromo);
            $this->PastikanBisaDipakai($voucher, $promo, $sekarang);

            $pemakaian = VoucherPemakaian::query()->where('IdVoucher', $voucher->Id)->where('UuidPenjualan', $uuidPenjualan)->first();

            if ($pemakaian?->Status === StatusPemakaianVoucher::Dipakai) {
                throw new PelanggaranAturanBisnis('VoucherSudahDipakai', "Voucher {$voucher->Kode} sudah dipakai penjualan ini.", 'Kode', 409);
            }

            $sisa = $voucher->AmbilSisaPakai();

            if ($sisa !== null) {
                $dipesanLain = VoucherPemakaian::query()
                    ->where('IdVoucher', $voucher->Id)
                    ->where('Status', StatusPemakaianVoucher::Dipesan->value)
                    ->where('UuidPenjualan', '!=', $uuidPenjualan)
                    ->where('DipesanSampai', '>', $sekarang)
                    ->count();
                $sisa -= $dipesanLain;

                if ($sisa <= 0) {
                    throw new PelanggaranAturanBisnis('VoucherHabis', $dipesanLain > 0
                        ? "Voucher {$voucher->Kode} sedang dipakai di transaksi lain."
                        : "Voucher {$voucher->Kode} sudah habis dipakai.", 'Kode', 409);
                }
            }

            $sampai = $sekarang->addMinutes(self::MENIT_PESAN);
            $pemakaian ??= new VoucherPemakaian(['IdVoucher' => $voucher->Id, 'UuidPenjualan' => $uuidPenjualan]);
            $pemakaian->fill(['Status' => StatusPemakaianVoucher::Dipesan, 'DipesanSampai' => $sampai, 'IdPerangkat' => $idPerangkat]);
            $pemakaian->save();

            return [
                'Voucher' => [
                    'Kode' => $voucher->Kode,
                    'UuidPromo' => $promo->Uuid,
                    'DipesanSampai' => $sampai->utc()->toIso8601ZuluString(),
                    'SisaPakai' => $sisa === null ? null : $sisa - 1,
                ],
                'Promo' => PromoBerlaku::PetakanUntukPos($promo),
            ];
        });
    }

    private function PastikanBisaDipakai(Voucher $voucher, Promo $promo, CarbonImmutable $sekarang): void
    {
        if ($voucher->Status !== StatusVoucher::Aktif) {
            throw new PelanggaranAturanBisnis('VoucherNonaktif', "Voucher {$voucher->Kode} sudah dinonaktifkan.", 'Kode');
        }

        if ($voucher->KedaluwarsaPada !== null && ! $sekarang->lessThan($voucher->KedaluwarsaPada)) {
            throw new PelanggaranAturanBisnis('VoucherKedaluwarsa', "Voucher {$voucher->Kode} sudah kedaluwarsa.", 'Kode');
        }

        $berakhir = $promo->SelesaiPada !== null && ! $sekarang->lessThan($promo->SelesaiPada);
        $belumMulai = $promo->MulaiPada !== null && $sekarang->lessThan($promo->MulaiPada);

        if ($promo->Status !== StatusPromo::Aktif || $berakhir || $belumMulai || $promo->AmbilKuotaTersisa() === 0) {
            throw new PelanggaranAturanBisnis('PromoTidakBerlaku', "Promo voucher {$voucher->Kode} sedang tidak berlaku.", 'Kode');
        }
    }
}
