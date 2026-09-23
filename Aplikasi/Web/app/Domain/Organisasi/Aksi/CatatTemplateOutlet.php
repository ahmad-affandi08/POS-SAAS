<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Organisasi\Model\Outlet;
use Illuminate\Support\Facades\DB;

/**
 * F-01 / P-03 (BR-P03.1, §25 no. 15): mencatat template & versi terbit yang diterapkan ke outlet. Versi terbit yang
 * lebih baru tidak mengubah outlet sampai template diterapkan lagi. Tidak berubah bila versi sama (kirim ganda aman).
 */
final class CatatTemplateOutlet
{
    public function __construct(private readonly PencatatAudit $audit) {}

    /** @return bool true bila versi yang tercatat berubah */
    public function Jalankan(Outlet $outlet, string $kodeTemplate, int $idVersi): bool
    {
        return DB::transaction(function () use ($outlet, $kodeTemplate, $idVersi): bool {
            $outlet = Outlet::query()->lockForUpdate()->findOrFail($outlet->Id);

            if ($outlet->IdTemplateSektorVersi === $idVersi && $outlet->TemplateSektor === $kodeTemplate) {
                return false;
            }

            $lama = ['TemplateSektor' => $outlet->TemplateSektor, 'IdTemplateSektorVersi' => $outlet->IdTemplateSektorVersi];
            $outlet->fill([
                'TemplateSektor' => $kodeTemplate,
                'IdTemplateSektorVersi' => $idVersi,
                'TemplateSektorDiterapkanPada' => now(),
            ])->save();
            $this->audit->Catat('outlet.template.ubah', $outlet, nilaiLama: $lama, nilaiBaru: ['TemplateSektor' => $kodeTemplate, 'IdTemplateSektorVersi' => $idVersi]);

            return true;
        });
    }
}
