<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tindakan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tindakan\Model\LanggananRingkasanTindakan;
use Illuminate\Support\Facades\DB;

/** Pengguna memilih menerima atau berhenti menerima ringkasan pagi Kotak Tindakan lewat WhatsApp (D-23 D bagian 4, D-33). */
final class UbahLanggananRingkasanTindakan
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(int $idPengguna, bool $aktif): void
    {
        DB::transaction(function () use ($idPengguna, $aktif): void {
            $baris = LanggananRingkasanTindakan::query()->where('IdPengguna', $idPengguna)->lockForUpdate()->first()
                ?? new LanggananRingkasanTindakan(['IdPengguna' => $idPengguna]);
            $lama = $baris->exists ? $baris->Aktif : null;
            $baris->Aktif = $aktif;
            $baris->save();

            if ($lama !== $aktif) {
                $this->audit->Catat('tindakan.ringkasan-whatsapp.ubah', $baris, $lama === null ? null : ['Aktif' => $lama], ['Aktif' => $aktif]);
            }
        });
    }
}
