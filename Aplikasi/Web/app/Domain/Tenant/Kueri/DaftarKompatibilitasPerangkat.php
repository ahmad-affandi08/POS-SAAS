<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Tenant\Enum\JenisKompatibilitas;
use App\Domain\Tenant\Enum\StatusKompatibilitas;
use App\Domain\Tenant\Model\KompatibilitasPerangkat;

/**
 * Hardware Compatibility List (PRD §17.2.5a, v1.98). Publik: hanya baris berstatus Tersertifikasi, Kompatibel, atau
 * Terbatas yang masih dipakai (atau ditandai tim), tanpa nama tenant. Pengelola: semua baris.
 */
final class DaftarKompatibilitasPerangkat
{
    /**
     * @return list<array{Uuid: string, Jenis: string, Nama: string, Sambungan: string|null, Status: string, LabelStatus: string, StatusOtomatis: string, StatusManual: string|null, Catatan: string|null, JumlahPerangkat: int, JumlahTenant: int, JumlahLolos: int, JumlahGagal: int, TerakhirDiujiPada: string|null, DisegarkanPada: string|null}>
     */
    public function Ambil(bool $publik): array
    {
        $urutan = [
            StatusKompatibilitas::Tersertifikasi->value => 0,
            StatusKompatibilitas::Kompatibel->value => 1,
            StatusKompatibilitas::Terbatas->value => 2,
            StatusKompatibilitas::BelumDiuji->value => 3,
        ];
        $baris = KompatibilitasPerangkat::query()->orderBy('Jenis')->orderBy('Nama')->get()
            ->filter(fn (KompatibilitasPerangkat $k): bool => ! $publik || (
                $k->AmbilStatus() !== StatusKompatibilitas::BelumDiuji && ($k->JumlahPerangkat > 0 || $k->StatusManual !== null)
            ))
            ->sortBy(fn (KompatibilitasPerangkat $k): string => $k->Jenis->value.'#'.$urutan[$k->AmbilStatus()->value].'#'.mb_strtolower($k->Nama));

        return array_values($baris->map(fn (KompatibilitasPerangkat $k): array => [
            'Uuid' => $k->Uuid,
            'Jenis' => $k->Jenis->value,
            'Nama' => $k->Nama,
            'Sambungan' => $k->Jenis === JenisKompatibilitas::Printer ? $k->Sambungan : null,
            'Status' => $k->AmbilStatus()->value,
            'LabelStatus' => $k->AmbilStatus()->AmbilLabel(),
            'StatusOtomatis' => $k->StatusOtomatis->value,
            'StatusManual' => $k->StatusManual?->value,
            'Catatan' => $k->Catatan,
            'JumlahPerangkat' => $k->JumlahPerangkat,
            'JumlahTenant' => $k->JumlahTenant,
            'JumlahLolos' => $k->JumlahLolos,
            'JumlahGagal' => $k->JumlahGagal,
            'TerakhirDiujiPada' => $k->TerakhirDiujiPada?->toIso8601String(),
            'DisegarkanPada' => $k->DisegarkanPada?->toIso8601String(),
        ])->all());
    }
}
