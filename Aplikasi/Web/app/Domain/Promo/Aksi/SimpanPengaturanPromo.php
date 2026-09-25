<?php

declare(strict_types=1);

namespace App\Domain\Promo\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Penjualan\Enum\ModeResolusiPromo;
use App\Domain\Promo\Model\PengaturanPromo;
use Illuminate\Support\Facades\DB;

/** Simpan mode resolusi konflik promo tenant (F-16c). Audit `promo.pengaturan`. */
final class SimpanPengaturanPromo
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(ModeResolusiPromo $mode, int $idPengguna): PengaturanPromo
    {
        return DB::transaction(function () use ($mode, $idPengguna): PengaturanPromo {
            $p = PengaturanPromo::query()->lockForUpdate()->first() ?? new PengaturanPromo;
            $lama = $p->exists ? $p->ModeResolusi->value : ModeResolusiPromo::Terbaik->value;
            $p->ModeResolusi = $mode;
            $p->save();

            if ($lama !== $mode->value) {
                $this->audit->Catat('promo.pengaturan', $p, ['ModeResolusi' => $lama], ['ModeResolusi' => $mode->value], idPengguna: $idPengguna);
            }

            return $p;
        });
    }
}
