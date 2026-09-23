<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Layanan;

use App\Domain\Pajak\Model\KelompokPajak;

/**
 * Kelompok pajak tenant aktif untuk halaman & katalog POS Tim 1 (tipe FE `OpsiKelompokPajak` + `Id`), dan peta
 * Uuid ↔ Id untuk form produk.
 *
 * SEMENTARA sampai T2-A (`Pajak\Kueri\DaftarKelompokPajak::AmbilOpsi()` dengan `Kategori`) digabung: dibaca langsung
 * tanpa kategori.
 */
final class OpsiKelompokPajakKatalog
{
    /** @var list<array{Id: int, Uuid: string, Nama: string, Kategori: string|null, LabelKategori: string}>|null */
    private ?array $opsi = null;

    /**
     * @return list<array{Id: int, Uuid: string, Nama: string, Kategori: string|null, LabelKategori: string}>
     */
    public function AmbilOpsi(): array
    {
        return $this->opsi ??= array_values(KelompokPajak::query()->orderBy('Nama')->get()
            ->map(fn (KelompokPajak $kelompok): array => ['Id' => $kelompok->Id, 'Uuid' => $kelompok->Uuid, 'Nama' => $kelompok->Nama, 'Kategori' => null, 'LabelKategori' => ''])
            ->all());
    }

    /**
     * Opsi untuk halaman (tanpa `Id`).
     *
     * @return list<array{Uuid: string, Nama: string, Kategori: string|null, LabelKategori: string}>
     */
    public function AmbilOpsiHalaman(): array
    {
        return array_map(self::TanpaId(...), $this->AmbilOpsi());
    }

    /**
     * @return array{Uuid: string, Nama: string, Kategori: string|null, LabelKategori: string}|null
     */
    public function CariBerdasarkanId(?int $id): ?array
    {
        foreach ($this->AmbilOpsi() as $opsi) {
            if ($opsi['Id'] === $id) {
                return self::TanpaId($opsi);
            }
        }

        return null;
    }

    /**
     * @param  array{Id: int, Uuid: string, Nama: string, Kategori: string|null, LabelKategori: string}  $opsi
     * @return array{Uuid: string, Nama: string, Kategori: string|null, LabelKategori: string}
     */
    private static function TanpaId(array $opsi): array
    {
        return ['Uuid' => $opsi['Uuid'], 'Nama' => $opsi['Nama'], 'Kategori' => $opsi['Kategori'], 'LabelKategori' => $opsi['LabelKategori']];
    }

    public function CariIdBerdasarkanUuid(string $uuid): ?int
    {
        foreach ($this->AmbilOpsi() as $opsi) {
            if ($opsi['Uuid'] === $uuid) {
                return $opsi['Id'];
            }
        }

        return null;
    }
}
