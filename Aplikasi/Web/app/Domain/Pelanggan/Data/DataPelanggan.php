<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Data;

use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;

/**
 * Masukan `SimpanPelanggan` (F-16a). `noHp` mentah (dinormalisasi aksi). Teks opsional kosong = null. `tag` sudah
 * dirapikan (unik, tanpa kosong). F-12: `aturKredit` = limit kredit (null = tidak boleh tempo) & termin ikut disimpan.
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
        public bool $aturKredit = false,
        public ?Uang $limitKredit = null,
        public int $terminHari = 30,
    ) {}
}
