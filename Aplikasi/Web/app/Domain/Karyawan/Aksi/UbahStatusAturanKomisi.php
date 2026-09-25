<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Karyawan\Enum\StatusAturanKomisi;
use App\Domain\Karyawan\Model\AturanKomisi;

/** Arsipkan/pulihkan aturan komisi (F-18, izin `karyawan.kelola`). Idempoten. Audit `aturan-komisi.arsipkan|pulihkan`. */
final class UbahStatusAturanKomisi
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(AturanKomisi $aturan, StatusAturanKomisi $status, int $idPengguna): AturanKomisi
    {
        if ($aturan->Status === $status) {
            return $aturan;
        }

        $lama = $aturan->Status;
        $aturan->Status = $status;
        $aturan->save();
        $this->audit->Catat($status === StatusAturanKomisi::Aktif ? 'aturan-komisi.pulihkan' : 'aturan-komisi.arsipkan', $aturan, ['Status' => $lama->value], ['Status' => $status->value], idPengguna: $idPengguna);

        return $aturan;
    }
}
