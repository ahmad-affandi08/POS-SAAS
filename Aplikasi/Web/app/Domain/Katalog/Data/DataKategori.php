<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Data;

/**
 * Isi form kategori (F-03 `SimpanKategori`). `idInduk` null = kategori akar; kedalaman maksimal
 * `config('katalog.Kategori.MaksimalKedalaman')` (3). `idStasiunDapur` (F-10a) hanya diterapkan bila `aturStasiun`
 * true, agar pemanggil lama (template, API) tidak menghapus rujukan stasiun tanpa sengaja.
 */
final readonly class DataKategori
{
    public function __construct(
        public string $nama,
        public ?int $idInduk,
        public int $urutan,
        public ?int $idStasiunDapur = null,
        public bool $aturStasiun = false,
    ) {}
}
