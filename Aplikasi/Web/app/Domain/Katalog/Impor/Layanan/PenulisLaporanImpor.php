<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Layanan;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Impor\Enum\StatusBarisImpor;
use App\Domain\Katalog\Impor\Model\ImporProduk;
use App\Domain\Katalog\Impor\Model\ImporProdukBaris;
use Generator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan impor yang bisa diunduh (F-03 BR-03.6): kolom asli berkas + "Nomor Baris", "Status Impor", "Galat". Jenis `galat`
 * = baris bermasalah saja (Galat & GagalDiterapkan), `semua` = seluruh baris. Berkas laporan galat bisa diperbaiki
 * lalu diunggah ulang (judul kolom asli tetap, sehingga pemetaan otomatis sama).
 */
final class PenulisLaporanImpor
{
    public function __construct(private readonly KonteksTenant $konteks) {}

    public function Alirkan(ImporProduk $impor, string $jenis, string $format): StreamedResponse
    {
        $judulAsli = array_values(array_column($impor->KolomSumber ?? [], 'Judul'));
        $hanyaGalat = $jenis !== 'semua';
        $idTenant = $impor->IdTenant;
        $idImpor = $impor->Id;
        $nama = 'laporan-impor-'.($hanyaGalat ? 'galat-' : '').($impor->DibuatPada?->format('Ymd-His') ?? $impor->Uuid);

        return PenulisTabel::Alirkan($format, $nama, [...$judulAsli, 'Nomor Baris', 'Status Impor', 'Galat'], fn (): Generator => $this->AmbilBaris($idTenant, $idImpor, $judulAsli, $hanyaGalat));
    }

    /**
     * @param  list<string>  $judulAsli
     * @return Generator<list<string>>
     */
    private function AmbilBaris(int $idTenant, int $idImpor, array $judulAsli, bool $hanyaGalat): Generator
    {
        // Respons dialirkan setelah kontroler selesai: pastikan scope tenant tetap tenant impor ini.
        $this->konteks->Atur($idTenant);

        $kueri = ImporProdukBaris::query()->where('IdImporProduk', $idImpor)
            ->when($hanyaGalat, fn ($k) => $k->whereIn('Status', [StatusBarisImpor::Galat->value, StatusBarisImpor::GagalDiterapkan->value]));

        foreach ($kueri->lazyById(500, 'Id') as $baris) {
            /** @var ImporProdukBaris $baris */
            $galat = array_map(fn (array $g): string => "{$g['Bidang']}: {$g['Pesan']}", $baris->Galat ?? []);

            yield [
                ...array_map(fn (string $judul): string => (string) ($baris->DataAsli[$judul] ?? ''), $judulAsli),
                (string) $baris->NomorBaris,
                $baris->Status->AmbilLabel(),
                implode('; ', $galat),
            ];
        }
    }
}
