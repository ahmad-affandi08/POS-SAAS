<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Data;

/**
 * Satu atribut varian induk (F-03), misal Ukuran = [S, M, L]. Maksimal 3 atribut dan 20 nilai per atribut
 * (`config('katalog.Varian')`).
 */
final readonly class DataAtributVarian
{
    /**
     * @param  list<string>  $nilai
     */
    public function __construct(
        public string $nama,
        public array $nilai,
    ) {}
}
