<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Layanan;

use App\Domain\Pajak\Kueri\DaftarKelompokPajak;

/**
 * Kelompok pajak tenant aktif untuk halaman & katalog POS Tim 1 (tipe FE `OpsiKelompokPajak` + `Id`), dan peta
 * Uuid ↔ Id untuk form produk. Sumber: `Pajak\Kueri\DaftarKelompokPajak::AmbilOpsi()` (domain Pajak, Tim 2),
 * disimpan per instans agar satu permintaan tidak mengulang kueri.
 */
final class OpsiKelompokPajakKatalog
{
    /** @var list<array{Id: int, Uuid: string, Nama: string, Kategori: string|null, LabelKategori: string}>|null */
    private ?array $opsi = null;

    public function __construct(private readonly DaftarKelompokPajak $daftarKelompokPajak) {}

    /**
     * @return list<array{Id: int, Uuid: string, Nama: string, Kategori: string|null, LabelKategori: string}>
     */
    public function AmbilOpsi(): array
    {
        return $this->opsi ??= $this->daftarKelompokPajak->AmbilOpsi();
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
