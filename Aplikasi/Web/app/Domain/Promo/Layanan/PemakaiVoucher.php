<?php

declare(strict_types=1);

namespace App\Domain\Promo\Layanan;

use App\Domain\Promo\Enum\StatusPemakaianVoucher;
use App\Domain\Promo\Enum\StatusVoucher;
use App\Domain\Promo\Kueri\PromoBerlaku;
use App\Domain\Promo\Model\Promo;
use App\Domain\Promo\Model\Voucher;
use App\Domain\Promo\Model\VoucherPemakaian;
use Carbon\CarbonImmutable;

/**
 * Layanan publik domain Promo untuk domain Penjualan (F-16c bagian 2): voucher penjualan POS.
 * - `AmbilUuidPromo`: promo voucher untuk konteks validasi ulang `MesinPromo` (voucher tidak dikenal = tanpa promo).
 * - `Pakai`: pesanan voucher menjadi `Dipakai` dan `JumlahDipakai` bertambah di transaksi DB penjualan (baris voucher
 *   dikunci). Idempoten per (voucher, penjualan). Voucher yang nonaktif, kedaluwarsa, atau melewati batas pakai tidak
 *   menggagalkan penjualan (sudah terjadi di kasir); masalahnya dikembalikan untuk tinjauan.
 * - `Lepaskan`: penjualan di-void → voucher yang dipakai dilepas dan bisa dipakai lagi.
 * - F-17 (v3.46): voucher yang dipesan pembeli di checkout toko online tercatat atas Uuid pesanan; `Pakai` dengan
 *   `$uuidCadangan` = Uuid pesanan itu memindahkannya ke penjualan yang menagih pesanan. `AmbilUntukPos` memberi kasir
 *   definisi promonya supaya keranjang bisa memuat voucher tanpa memesan ulang.
 */
final class PemakaiVoucher
{
    public function AmbilUuidPromo(?string $kode): ?string
    {
        if ($kode === null || trim($kode) === '') {
            return null;
        }

        $idPromo = Voucher::query()->where('Kode', Voucher::RapikanKode($kode))->value('IdPromo');

        return $idPromo === null ? null : Promo::query()->whereKey($idPromo)->value('Uuid');
    }

    /**
     * @return list<string> masalah untuk alasan tinjauan
     */
    public function Pakai(string $kode, string $uuidPenjualan, int $idPenjualan, CarbonImmutable $waktuPenjualan, ?string $uuidCadangan = null): array
    {
        $kode = Voucher::RapikanKode($kode);
        $voucher = Voucher::query()->where('Kode', $kode)->lockForUpdate()->first();

        if ($voucher === null) {
            return ["voucher {$kode} tidak dikenal server"];
        }

        $pemakaian = VoucherPemakaian::query()->where('IdVoucher', $voucher->Id)->where('UuidPenjualan', $uuidPenjualan)->first();

        if ($pemakaian?->Status === StatusPemakaianVoucher::Dipakai) {
            return [];
        }

        if ($pemakaian === null && $uuidCadangan !== null) {
            $pemakaian = VoucherPemakaian::query()
                ->where('IdVoucher', $voucher->Id)
                ->where('UuidPenjualan', $uuidCadangan)
                ->where('Status', StatusPemakaianVoucher::Dipesan->value)
                ->first();
            $pemakaian?->fill(['UuidPenjualan' => $uuidPenjualan]);
        }

        $masalah = [];

        if ($pemakaian === null || $pemakaian->Status !== StatusPemakaianVoucher::Dipesan) {
            $masalah[] = "voucher {$kode} tidak dipesan online untuk penjualan ini";
        }

        if ($voucher->Status !== StatusVoucher::Aktif) {
            $masalah[] = "voucher {$kode} nonaktif";
        }

        if ($voucher->KedaluwarsaPada !== null && ! $waktuPenjualan->lessThan($voucher->KedaluwarsaPada)) {
            $masalah[] = "voucher {$kode} sudah kedaluwarsa saat transaksi";
        }

        if ($voucher->MaksimalPakai !== null && $voucher->JumlahDipakai >= $voucher->MaksimalPakai) {
            $masalah[] = "voucher {$kode} melewati batas pakai ({$voucher->MaksimalPakai})";
        }

        $pemakaian ??= new VoucherPemakaian(['IdVoucher' => $voucher->Id, 'UuidPenjualan' => $uuidPenjualan]);
        $pemakaian->fill(['Status' => StatusPemakaianVoucher::Dipakai, 'IdPenjualan' => $idPenjualan, 'DipesanSampai' => null]);
        $pemakaian->save();
        $voucher->JumlahDipakai++;
        $voucher->save();

        return $masalah;
    }

    /**
     * Voucher pesanan toko online untuk dimuat kasir (bentuk promo sama dengan `GET /promo`); null bila kode tidak dikenal.
     *
     * @return array{Kode: string, UuidPromo: string, NamaPromo: string, Promo: array<string, mixed>}|null
     */
    public function AmbilUntukPos(?string $kode): ?array
    {
        if ($kode === null || trim($kode) === '') {
            return null;
        }

        $voucher = Voucher::query()->where('Kode', Voucher::RapikanKode($kode))->first();
        $promo = $voucher === null ? null : Promo::query()->find($voucher->IdPromo);

        return $voucher === null || $promo === null ? null : [
            'Kode' => $voucher->Kode,
            'UuidPromo' => $promo->Uuid,
            'NamaPromo' => $promo->Nama,
            'Promo' => PromoBerlaku::PetakanUntukPos($promo),
        ];
    }

    /**
     * v3.46: pesanan voucher atas [uuidPemesan] (mis. Uuid pesanan toko online) dilepas bila masih `Dipesan`, misalnya
     * kasir menagih pesanan tanpa voucher-nya. Voucher yang sudah dipakai tidak tersentuh.
     */
    public function LepasPesanan(string $kode, string $uuidPemesan): void
    {
        $voucher = Voucher::query()->where('Kode', Voucher::RapikanKode($kode))->first();

        if ($voucher === null) {
            return;
        }

        VoucherPemakaian::query()
            ->where('IdVoucher', $voucher->Id)
            ->where('UuidPenjualan', $uuidPemesan)
            ->where('Status', StatusPemakaianVoucher::Dipesan->value)
            ->update(['Status' => StatusPemakaianVoucher::Dilepas->value, 'DipesanSampai' => null]);
    }

    public function Lepaskan(int $idPenjualan): void
    {
        $daftar = VoucherPemakaian::query()
            ->where('IdPenjualan', $idPenjualan)
            ->where('Status', StatusPemakaianVoucher::Dipakai->value)
            ->get();

        foreach ($daftar as $pemakaian) {
            $voucher = Voucher::query()->whereKey($pemakaian->IdVoucher)->lockForUpdate()->firstOrFail();
            $voucher->JumlahDipakai = max(0, $voucher->JumlahDipakai - 1);
            $voucher->save();
            $pemakaian->update(['Status' => StatusPemakaianVoucher::Dilepas]);
        }
    }
}
