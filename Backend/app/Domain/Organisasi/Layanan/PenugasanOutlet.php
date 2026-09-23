<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Layanan;

use App\Domain\Organisasi\Model\OutletPengguna;

/**
 * Menyelaraskan baris `OutletPengguna` satu anggota dengan akses yang ditetapkan. Anggota dengan akses semua
 * outlet tidak memerlukan baris penugasan. Dipanggil di dalam transaksi dengan tenant aktif sudah ditetapkan.
 */
final class PenugasanOutlet
{
    /**
     * @param  list<int>  $idOutlet
     */
    public function Sinkronkan(int $idPengguna, int $idPeran, bool $semuaOutlet, array $idOutlet): void
    {
        $idOutlet = $semuaOutlet ? [] : $idOutlet;

        OutletPengguna::query()->where('IdPengguna', $idPengguna)->whereNotIn('IdOutlet', $idOutlet === [] ? [0] : $idOutlet)->delete();
        OutletPengguna::query()->where('IdPengguna', $idPengguna)->update(['IdPeran' => $idPeran]);
        $ada = array_map('intval', OutletPengguna::query()->where('IdPengguna', $idPengguna)->pluck('IdOutlet')->all());

        foreach (array_diff($idOutlet, $ada) as $id) {
            OutletPengguna::query()->create(['IdOutlet' => $id, 'IdPengguna' => $idPengguna, 'IdPeran' => $idPeran]);
        }
    }
}
