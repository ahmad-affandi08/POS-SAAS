<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Katalog\Kueri;

use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Tenant\Model\Fitur;
use App\Domain\Tenant\Model\HargaPaket;
use App\Domain\Tenant\Model\Paket;

/**
 * Kueri halaman katalog Platform Pengelola (P-04).
 */
final class DaftarKatalog
{
    /**
     * @return list<array{Kunci: string, Nama: string, Modul: string, Keterangan: string|null}>
     */
    public function AmbilFitur(): array
    {
        return array_values(Fitur::query()->orderBy('Modul')->orderBy('Kunci')->get()->map(fn (Fitur $fitur): array => [
            'Kunci' => $fitur->Kunci,
            'Nama' => $fitur->Nama,
            'Modul' => $fitur->Modul,
            'Keterangan' => $fitur->Keterangan,
        ])->all());
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function AmbilPaket(): array
    {
        $hargaTerbit = HargaPaket::query()
            ->where('Status', StatusDataMaster::Terbit->value)
            ->whereDate('BerlakuMulai', '<=', now('Asia/Jakarta')->toDateString())
            ->orderByDesc('BerlakuMulai')
            ->get()
            ->unique('IdPaket')
            ->keyBy('IdPaket');

        return array_values(Paket::query()->with('Fitur')->orderBy('Urutan')->orderBy('Kode')->get()->map(
            fn (Paket $paket): array => [
                ...self::PetakanPaket($paket),
                'HargaBulananBerlaku' => $hargaTerbit->get($paket->Id)?->HargaBulanan,
            ],
        )->all());
    }

    /**
     * @return array<string, mixed>
     */
    public static function PetakanPaket(Paket $paket): array
    {
        return [
            'Uuid' => $paket->Uuid,
            'Kode' => $paket->Kode,
            'Nama' => $paket->Nama,
            'Keterangan' => $paket->Keterangan,
            'Status' => $paket->Status->value,
            'HargaNegosiasi' => $paket->HargaNegosiasi,
            'MasaTrialHari' => $paket->MasaTrialHari,
            'Urutan' => $paket->Urutan,
            'Batas' => $paket->AmbilBatas(),
            'KunciFitur' => $paket->AmbilKunciFitur(),
        ];
    }
}
