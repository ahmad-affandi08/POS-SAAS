<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Data;

/**
 * D-22: isian pengguna yang ditambahkan langsung oleh admin tenant. Email kosong = karyawan hanya kasir (masuk aplikasi
 * kasir dengan PIN, tanpa akses back-office); email terisi wajib disertai kata sandi awal.
 */
final readonly class DataPenggunaBaru
{
    public function __construct(
        public string $nama,
        public ?string $email,
        public ?string $noHp,
        public ?string $kataSandi,
        public ?string $pin,
    ) {}
}
