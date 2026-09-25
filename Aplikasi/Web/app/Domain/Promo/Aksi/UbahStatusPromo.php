<?php

declare(strict_types=1);

namespace App\Domain\Promo\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Promo\Enum\StatusPromo;
use App\Domain\Promo\Model\Promo;
use Illuminate\Support\Facades\DB;

/** Arsipkan/pulihkan promo (F-16c). Audit `promo.arsipkan|pulihkan`. */
final class UbahStatusPromo
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(Promo $promo, StatusPromo $status, int $idPengguna): Promo
    {
        if ($promo->Status === $status) {
            return $promo;
        }

        return DB::transaction(function () use ($promo, $status, $idPengguna): Promo {
            $lama = $promo->Status->value;
            $promo->Status = $status;
            $promo->save();
            $this->audit->Catat($status === StatusPromo::Aktif ? 'promo.pulihkan' : 'promo.arsipkan', $promo, ['Status' => $lama], ['Status' => $status->value], idPengguna: $idPengguna);

            return $promo;
        });
    }
}
