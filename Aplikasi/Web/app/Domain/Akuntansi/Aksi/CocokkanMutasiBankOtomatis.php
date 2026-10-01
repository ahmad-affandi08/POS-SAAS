<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Enum\StatusMutasiBank;
use App\Domain\Akuntansi\Layanan\PencariKandidatMutasi;
use App\Domain\Akuntansi\Model\MutasiBank;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use Illuminate\Support\Facades\DB;

/**
 * Cocokkan otomatis (FIN-09): mutasi Belum cocok satu akun yang punya **tepat satu** kandidat jurnal, dan kandidat
 * itu juga hanya cocok untuk satu mutasi, dicocokkan. Yang ambigu (dua transfer bernominal sama di tanggal berdekatan)
 * dibiarkan untuk dipilih manual — salah cocok lebih mahal daripada satu klik. Audit `mutasi-bank.cocok-otomatis`.
 */
final class CocokkanMutasiBankOtomatis
{
    public function __construct(
        private readonly PencariKandidatMutasi $kandidat,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(int $idAkun, int $idPengguna): int
    {
        return DB::transaction(function () use ($idAkun, $idPengguna): int {
            $daftar = MutasiBank::query()->where('IdAkun', $idAkun)->where('Status', StatusMutasiBank::BelumCocok->value)->orderBy('Tanggal')->orderBy('Id')->lockForUpdate()->get();
            $usulan = [];
            $pemakai = [];

            foreach ($daftar as $mutasi) {
                $calon = $this->kandidat->Cari($mutasi, 2);

                if ($calon->count() === 1) {
                    $idBaris = (int) $calon->first()?->Id;
                    $usulan[$mutasi->Id] = $idBaris;
                    $pemakai[$idBaris] = ($pemakai[$idBaris] ?? 0) + 1;
                }
            }

            $dicocokkan = 0;

            foreach ($daftar as $mutasi) {
                $idBaris = $usulan[$mutasi->Id] ?? null;

                if ($idBaris !== null && $pemakai[$idBaris] === 1) {
                    $mutasi->fill(['Status' => StatusMutasiBank::Cocok, 'IdJurnalDetail' => $idBaris, 'DiputuskanOleh' => $idPengguna, 'DiputuskanPada' => now()])->save();
                    $dicocokkan++;
                }
            }

            if ($dicocokkan > 0) {
                $this->audit->Catat('mutasi-bank.cocok-otomatis', null, nilaiBaru: ['IdAkun' => $idAkun, 'Jumlah' => $dicocokkan], idPengguna: $idPengguna);
            }

            return $dicocokkan;
        });
    }
}
