<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Model\Jurnal;
use App\Domain\Akuntansi\Model\KunciPeriode;
use App\Domain\Organisasi\Kueri\DaftarAnggota;

/**
 * Status tutup tahun F-15 (J-15.1): tahun dianggap ditutup bila jurnal penutupnya (sumber `TutupTahun`, IdSumber =
 * tahun) ada. Juga daftar tahun untuk halaman Tutup buku.
 */
final class StatusTutupTahun
{
    public const JUMLAH_TAHUN = 5;

    public function __construct(private readonly DaftarAnggota $anggota) {}

    public function CekDitutup(int $tahun): bool
    {
        return Jurnal::query()->where('JenisSumber', JenisSumberJurnal::TutupTahun->value)->where('IdSumber', $tahun)->exists();
    }

    /**
     * Tahun lalu dan empat tahun sebelumnya (terbaru dulu).
     *
     * @return list<array{Tahun: int, BulanTerkunci: int, Ditutup: bool, DitutupPada: string|null, DitutupOleh: string|null, NomorJurnal: string|null, UuidJurnal: string|null}>
     */
    public function Daftar(int $idTenant, int $tahunIni): array
    {
        $dari = $tahunIni - self::JUMLAH_TAHUN;
        $jurnal = Jurnal::query()
            ->where('JenisSumber', JenisSumberJurnal::TutupTahun->value)
            ->whereBetween('IdSumber', [$dari, $tahunIni - 1])
            ->get(['Uuid', 'IdSumber', 'Nomor', 'DibuatPada', 'DibuatOleh'])
            ->keyBy('IdSumber');
        $terkunci = [];

        foreach (KunciPeriode::query()->where('Periode', '>=', $dari.'-01')->pluck('Periode') as $periode) {
            $tahun = (int) substr($periode, 0, 4);
            $terkunci[$tahun] = ($terkunci[$tahun] ?? 0) + 1;
        }

        $nama = $this->anggota->AmbilNamaPengguna($idTenant, array_values(array_filter($jurnal->pluck('DibuatOleh')->all(), 'is_int')));
        $hasil = [];

        for ($tahun = $tahunIni - 1; $tahun >= $dari; $tahun--) {
            /** @var Jurnal|null $j */
            $j = $jurnal->get($tahun);
            $hasil[] = [
                'Tahun' => $tahun,
                'BulanTerkunci' => $terkunci[$tahun] ?? 0,
                'Ditutup' => $j !== null,
                'DitutupPada' => $j?->DibuatPada?->toIso8601String(),
                'DitutupOleh' => $j?->DibuatOleh === null ? null : ($nama[$j->DibuatOleh] ?? null),
                'NomorJurnal' => $j?->Nomor,
                'UuidJurnal' => $j?->Uuid,
            ];
        }

        return $hasil;
    }
}
