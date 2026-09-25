<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Pembelian\Model\Pemasok;
use Illuminate\Support\Facades\DB;

/**
 * Menonaktifkan/mengaktifkan pemasok (F-04 fase 1). Pemasok nonaktif tidak bisa dipilih di dokumen baru; dokumen
 * lama tetap menautnya. Audit `pemasok.nonaktifkan`/`pemasok.aktifkan`.
 */
final class UbahStatusPemasok
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(Pemasok $pemasok, bool $aktif, int $idPengguna): Pemasok
    {
        return DB::transaction(function () use ($pemasok, $aktif, $idPengguna): Pemasok {
            $terkunci = Pemasok::query()->whereKey($pemasok->Id)->lockForUpdate()->firstOrFail();

            if ($terkunci->Aktif === $aktif) {
                return $terkunci;
            }

            $terkunci->Aktif = $aktif;
            $terkunci->save();
            $this->audit->Catat($aktif ? 'pemasok.aktifkan' : 'pemasok.nonaktifkan', $terkunci, ['Aktif' => ! $aktif], ['Aktif' => $aktif], idPengguna: $idPengguna);

            return $terkunci;
        });
    }
}
