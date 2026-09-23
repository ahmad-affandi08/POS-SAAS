<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Data;

/**
 * Akun baru yang dibuat saat menerima undangan (email diambil dari undangan, bukan dari isian).
 */
final readonly class DataAkunBaru
{
    public function __construct(
        public string $nama,
        public ?string $noHp,
        public string $kataSandi,
    ) {}
}
