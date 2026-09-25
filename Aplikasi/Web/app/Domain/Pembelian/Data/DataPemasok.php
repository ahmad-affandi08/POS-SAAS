<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Data;

/**
 * Masukan `SimpanPemasok` (F-04 fase 1). `terminHari` 0 = tunai. Teks opsional kosong = null.
 */
final readonly class DataPemasok
{
    public function __construct(
        public string $kode,
        public string $nama,
        public ?string $namaKontak,
        public ?string $noHp,
        public ?string $email,
        public ?string $alamat,
        public ?string $npwp,
        public bool $pkp,
        public int $terminHari,
        public ?string $namaBank,
        public ?string $nomorRekening,
        public ?string $atasNamaRekening,
        public ?string $catatan,
        public ?int $idPengguna,
    ) {}
}
