<?php

declare(strict_types=1);

namespace App\Domain\Promo\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Promo\Enum\StatusVoucher;
use App\Domain\Promo\Model\Voucher;
use Illuminate\Support\Facades\DB;

/** Nonaktifkan/aktifkan voucher (F-16c bagian 2). Voucher nonaktif tidak bisa dipesan kasir. Audit `voucher.nonaktifkan|aktifkan`. */
final class UbahStatusVoucher
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(Voucher $voucher, StatusVoucher $status, int $idPengguna): Voucher
    {
        if ($voucher->Status === $status) {
            return $voucher;
        }

        return DB::transaction(function () use ($voucher, $status, $idPengguna): Voucher {
            $lama = $voucher->Status->value;
            $voucher->Status = $status;
            $voucher->save();
            $this->audit->Catat($status === StatusVoucher::Aktif ? 'voucher.aktifkan' : 'voucher.nonaktifkan', $voucher, ['Status' => $lama], ['Status' => $status->value], idPengguna: $idPengguna);

            return $voucher;
        });
    }
}
