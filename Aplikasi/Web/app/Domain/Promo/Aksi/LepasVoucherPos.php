<?php

declare(strict_types=1);

namespace App\Domain\Promo\Aksi;

use App\Domain\Promo\Enum\StatusPemakaianVoucher;
use App\Domain\Promo\Model\Voucher;
use App\Domain\Promo\Model\VoucherPemakaian;
use Illuminate\Support\Facades\DB;

/**
 * Kasir menghapus voucher dari keranjang atau membatalkan transaksi (F-16c bagian 2): pesanan voucher untuk penjualan
 * perangkat itu dilepas agar bisa dipakai transaksi lain. Idempoten; voucher yang sudah dipakai tidak berubah.
 */
final class LepasVoucherPos
{
    public function Jalankan(string $kode, string $uuidPenjualan): void
    {
        DB::transaction(function () use ($kode, $uuidPenjualan): void {
            $voucher = Voucher::query()->where('Kode', Voucher::RapikanKode($kode))->lockForUpdate()->first();

            if ($voucher === null) {
                return;
            }

            VoucherPemakaian::query()
                ->where('IdVoucher', $voucher->Id)
                ->where('UuidPenjualan', $uuidPenjualan)
                ->where('Status', StatusPemakaianVoucher::Dipesan->value)
                ->update(['Status' => StatusPemakaianVoucher::Dilepas->value, 'DipesanSampai' => null]);
        });
    }
}
