<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Data;

/**
 * Isian area meja per outlet (F-10a).
 */
final readonly class DataAreaMeja
{
    public function __construct(
        public string $nama,
        public int $urutan,
    ) {}
}
