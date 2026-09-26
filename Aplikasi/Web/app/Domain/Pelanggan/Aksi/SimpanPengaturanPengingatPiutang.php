<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pelanggan\Model\PengaturanPengingatPiutang;
use Illuminate\Support\Facades\DB;

/**
 * Simpan pengaturan pengingat piutang otomatis (D-23 D bagian 4b, izin `pelanggan.kelola`): aktif, berapa hari sebelum
 * jatuh tempo (0–14; 0 = pada hari jatuh tempo), dan sekali lagi sehari setelah lewat jatuh tempo. Audit
 * `piutang.pengingat.pengaturan`.
 */
final class SimpanPengaturanPengingatPiutang
{
    public const MAKS_HARI_SEBELUM = 14;

    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(bool $aktif, int $hariSebelum, bool $ingatkanSaatLewat, int $idPengguna): void
    {
        if ($hariSebelum < 0 || $hariSebelum > self::MAKS_HARI_SEBELUM) {
            throw new PelanggaranAturanBisnis('HariSebelumTidakValid', 'Pengingat dikirim 0–'.self::MAKS_HARI_SEBELUM.' hari sebelum jatuh tempo.', 'HariSebelum');
        }

        DB::transaction(function () use ($aktif, $hariSebelum, $ingatkanSaatLewat, $idPengguna): void {
            $p = PengaturanPengingatPiutang::query()->lockForUpdate()->first() ?? new PengaturanPengingatPiutang;
            $lama = ['Aktif' => $p->Aktif, 'HariSebelum' => $p->HariSebelum, 'IngatkanSaatLewat' => $p->IngatkanSaatLewat];
            $baru = ['Aktif' => $aktif, 'HariSebelum' => $hariSebelum, 'IngatkanSaatLewat' => $ingatkanSaatLewat];
            $p->fill($baru);
            $p->save();

            if ($lama !== $baru) {
                $this->audit->Catat('piutang.pengingat.pengaturan', $p, $lama, $baru, idPengguna: $idPengguna);
            }
        });
    }
}
