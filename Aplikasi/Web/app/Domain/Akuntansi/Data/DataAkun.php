<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Data;

use App\Domain\Akuntansi\Enum\TipeAkun;

/**
 * Isian akun bagan akun (F-13a). `kontra` = saldo normal kebalikan tipenya; `kasBank` = akun kas/bank (hanya aset
 * non-kontra). `uuidInduk` hanya dipakai saat menambah akun anak (induk tidak bisa dipindah).
 */
final readonly class DataAkun
{
    public function __construct(
        public string $kode,
        public string $nama,
        public TipeAkun $tipe,
        public bool $kontra,
        public bool $kasBank,
        public ?string $uuidInduk = null,
    ) {}
}
