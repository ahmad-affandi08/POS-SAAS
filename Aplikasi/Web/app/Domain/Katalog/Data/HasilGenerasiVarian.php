<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Data;

/**
 * Hasil generasi varian: Uuid anak yang dibuat dan nama kombinasi yang dilewati karena sudah ada (idempoten).
 */
final readonly class HasilGenerasiVarian
{
    /**
     * @param  list<string>  $dibuat
     * @param  list<string>  $dilewati
     */
    public function __construct(
        public array $dibuat,
        public array $dilewati,
    ) {}
}
