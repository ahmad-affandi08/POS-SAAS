<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;

/**
 * Satu baris item outbox `Penjualan.Buat` (F-07b). `uuidProdukSatuan` null = satuan dasar. `kodePajak` null = semua
 * pajak dokumen berlaku, `[]` = baris tanpa pajak. `hargaTermasukPajak` null = ikut dokumen. F-18: [uuidKaryawan] staf
 * yang melayani baris (komisi dibagi rata; kosong = tanpa komisi).
 */
final readonly class DataBarisPenjualanPos
{
    /**
     * @param  list<array{UuidPilihan: string, Nama: string, Harga: string}>  $pilihan
     * @param  list<string>|null  $kodePajak
     * @param  list<string>  $uuidKaryawan
     */
    public function __construct(
        public string $uuid,
        public string $uuidProduk,
        public ?string $uuidProdukSatuan,
        public Kuantitas $jumlah,
        public Uang $hargaSatuan,
        public Uang $hargaPilihan,
        public array $pilihan,
        public ?bool $hargaTermasukPajak,
        public ?array $kodePajak,
        public ?DataDiskonManual $diskonManual,
        public ?string $catatan,
        public array $uuidKaryawan = [],
    ) {}
}
