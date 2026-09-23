<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Kueri;

use App\Domain\Pengelola\Konten\Data\DataDokumenLegal;
use App\Domain\Tenant\Enum\JenisDokumenLegal;
use App\Domain\Tenant\Enum\StatusDokumenLegal;
use App\Domain\Tenant\Model\DokumenLegal;

/**
 * Halaman dokumen legal (P-06). Status tampilan dihitung dari tanggal (BR-P06.4).
 */
final class DaftarDokumenLegal
{
    /**
     * @return list<array<string, mixed>>
     */
    public function Ambil(): array
    {
        $hariIni = now('Asia/Jakarta')->toDateString();
        $semua = DokumenLegal::query()
            ->orderByDesc('Versi')
            ->get(['Id', 'Uuid', 'Jenis', 'Versi', 'Judul', 'RingkasanPerubahan', 'Materiil', 'BerlakuMulai', 'Status', 'DiterbitkanPada'])
            ->groupBy(fn (DokumenLegal $dokumen) => $dokumen->Jenis->value);
        $hasil = [];

        foreach (JenisDokumenLegal::cases() as $jenis) {
            $versi = $semua->get($jenis->value, collect());
            $berlaku = $versi
                ->filter(fn (DokumenLegal $dokumen) => $dokumen->Status === StatusDokumenLegal::Terbit
                    && $dokumen->BerlakuMulai->toDateString() <= $hariIni)
                ->sortByDesc(fn (DokumenLegal $dokumen) => $dokumen->BerlakuMulai->toDateString())
                ->first();

            $hasil[] = [
                'Jenis' => $jenis->value,
                'Label' => $jenis->AmbilLabel(),
                'WajibRegistrasi' => in_array($jenis, JenisDokumenLegal::AmbilWajibRegistrasi(), true),
                'Versi' => array_values($versi->map(fn (DokumenLegal $dokumen): array => [
                    ...self::Petakan($dokumen),
                    'StatusTampilan' => match (true) {
                        $dokumen->Status === StatusDokumenLegal::Draf => 'Draf',
                        $dokumen->BerlakuMulai->toDateString() > $hariIni => 'Terjadwal',
                        $berlaku?->Id === $dokumen->Id => 'Berlaku',
                        default => 'Digantikan',
                    },
                ])->all()),
            ];
        }

        return $hasil;
    }

    public function AmbilDasarDrafBaru(JenisDokumenLegal $jenis): DataDokumenLegal
    {
        $terakhir = DokumenLegal::query()->where('Jenis', $jenis->value)->orderByDesc('Versi')->first();

        return new DataDokumenLegal(
            jenis: $jenis,
            judul: $terakhir === null ? $jenis->AmbilLabel() : $terakhir->Judul,
            isi: $terakhir === null ? "# {$jenis->AmbilLabel()}\n\nTulis isi dokumen di sini." : $terakhir->Isi,
            ringkasanPerubahan: null,
            materiil: false,
            berlakuMulai: now('Asia/Jakarta')->toDateString(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function Petakan(DokumenLegal $dokumen): array
    {
        return [
            'Uuid' => $dokumen->Uuid,
            'Jenis' => $dokumen->Jenis->value,
            'Versi' => $dokumen->Versi,
            'Judul' => $dokumen->Judul,
            'RingkasanPerubahan' => $dokumen->RingkasanPerubahan,
            'Materiil' => $dokumen->Materiil,
            'BerlakuMulai' => $dokumen->BerlakuMulai->toDateString(),
            'Status' => $dokumen->Status->value,
            'DiterbitkanPada' => $dokumen->DiterbitkanPada?->toIso8601String(),
        ];
    }
}
