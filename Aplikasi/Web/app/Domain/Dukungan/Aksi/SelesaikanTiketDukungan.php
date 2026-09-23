<?php

declare(strict_types=1);

namespace App\Domain\Dukungan\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Dukungan\Enum\StatusTiketDukungan;
use App\Domain\Dukungan\Layanan\PenulisPesanTiket;
use App\Domain\Dukungan\Model\TiketDukungan;
use Illuminate\Support\Facades\DB;

/**
 * Pengguna tenant menandai tiketnya selesai (P-09). Tiket tetap bisa dibuka lagi dengan membalas dalam 7 hari,
 * lalu ditutup otomatis.
 */
final class SelesaikanTiketDukungan
{
    public function __construct(private readonly PenulisPesanTiket $penulisPesan) {}

    public function Jalankan(string $namaPengguna, TiketDukungan $tiket): TiketDukungan
    {
        return DB::transaction(function () use ($namaPengguna, $tiket): TiketDukungan {
            $tiket = TiketDukungan::query()->lockForUpdate()->findOrFail($tiket->Id);

            if (! $tiket->Status->CekTerbuka()) {
                throw new PelanggaranAturanBisnis('P-09', "Tiket sudah berstatus {$tiket->Status->AmbilLabel()}.");
            }

            $tiket->Status = StatusTiketDukungan::Selesai;
            $tiket->DiselesaikanPada = now();
            $tiket->save();

            $this->penulisPesan->TulisSistem($tiket, "{$namaPengguna} menandai tiket ini selesai.");

            return $tiket;
        });
    }
}
