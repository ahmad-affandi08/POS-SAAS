<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Referensi\Enum\KeputusanTinjauan;
use App\Domain\Pengelola\Referensi\Model\PersetujuanDataMaster;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Carbon\CarbonInterface;

/**
 * Aturan four-eyes bersama untuk data master bertanggal (BR-P02.2): pengaju tidak meninjau drafnya sendiri,
 * satu orang satu keputusan per putaran pengajuan, dan hanya keputusan setelah `DiajukanPada` terakhir yang dihitung.
 */
final class TinjauanDataMaster
{
    public function CatatKeputusan(
        string $jenisData,
        int $idData,
        ?int $idPengaju,
        CarbonInterface $diajukanPada,
        PenggunaPengelola $peninjau,
        KeputusanTinjauan $keputusan,
        ?string $catatan,
    ): int {
        if ($idPengaju !== null && $idPengaju === $peninjau->Id) {
            throw new PelanggaranAturanBisnis('BR-P02.2', 'Pengaju tidak boleh meninjau pengajuannya sendiri. Minta anggota lain meninjau.');
        }

        if ($keputusan === KeputusanTinjauan::Tolak && ($catatan === null || trim($catatan) === '')) {
            throw new PelanggaranAturanBisnis('CatatanWajib', 'Tulis alasan penolakan agar pengaju bisa memperbaiki.', 'Catatan');
        }

        $putaran = PersetujuanDataMaster::query()
            ->where('JenisData', $jenisData)
            ->where('IdData', $idData)
            ->where('DibuatPada', '>=', $diajukanPada);

        if ((clone $putaran)->where('IdPenggunaPengelola', $peninjau->Id)->exists()) {
            throw new PelanggaranAturanBisnis('BR-P02.2', 'Anda sudah memberi keputusan untuk pengajuan ini. Persetujuan berikutnya harus dari anggota lain.');
        }

        PersetujuanDataMaster::query()->create([
            'JenisData' => $jenisData,
            'IdData' => $idData,
            'IdPenggunaPengelola' => $peninjau->Id,
            'Keputusan' => $keputusan,
            'Catatan' => $catatan,
        ]);

        return (clone $putaran)->where('Keputusan', KeputusanTinjauan::Setuju->value)->count();
    }
}
