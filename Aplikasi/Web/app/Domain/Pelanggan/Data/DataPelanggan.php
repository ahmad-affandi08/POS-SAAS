<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Data;

use Carbon\CarbonImmutable;

/**
 * Masukan `SimpanPelanggan` (F-16a). `noHp` mentah (dinormalisasi aksi). Teks opsional kosong = null. `tag` sudah
 * dirapikan (unik, tanpa kosong).
 */
final readonly class DataPelanggan
{
    /**
     * @param  list<string>  $tag
     */
    public function __construct(
        public string $nama,
        public string $noHp,
        public ?string $email,
        public ?CarbonImmutable $tanggalLahir,
        public ?string $alamat,
        public array $tag,
        public ?string $catatan,
        public bool $setujuPemasaran,
        public ?int $idPengguna,
    ) {}
}
