<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Data;

use App\Domain\Bersama\Nilai\Uang;

/**
 * Hasil `CatatMutasiStok` (DesainF05a C.2): baris per `kunciBaris`, dan `sudahAda` = dokumen ini sudah pernah
 * dicatat (pemutaran ulang idempoten, tidak ada baris baru).
 */
final readonly class HasilCatatMutasi
{
    /**
     * @param  array<string, HasilBarisMutasi>  $baris  kunci = kunciBaris
     */
    public function __construct(
        public array $baris,
        public bool $sudahAda,
    ) {}

    /**
     * Baris keluar yang stoknya tidak cukup (BR-05.2) namun tetap dicatat karena `abaikanBatasMinus` (F-07b).
     *
     * @return list<HasilBarisMutasi>
     */
    public function AmbilBarisStokTidakCukup(): array
    {
        return array_values(array_filter($this->baris, fn (HasilBarisMutasi $b): bool => $b->stokTidakCukup));
    }

    /** Σ perubahan nilai persediaan (bertanda). */
    public function TotalHpp(): Uang
    {
        return array_reduce($this->baris, fn (Uang $total, HasilBarisMutasi $b): Uang => $total->Tambah($b->totalHpp), Uang::Nol());
    }

    /** Σ nilai yang diminta dokumen (bertanda). */
    public function TotalNilaiDiminta(): Uang
    {
        return array_reduce($this->baris, fn (Uang $total, HasilBarisMutasi $b): Uang => $total->Tambah($b->nilaiDiminta), Uang::Nol());
    }

    /** Σ selisih HPP (totalHpp − nilaiDiminta). */
    public function TotalSelisih(): Uang
    {
        return array_reduce($this->baris, fn (Uang $total, HasilBarisMutasi $b): Uang => $total->Tambah($b->selisihHpp), Uang::Nol());
    }
}
