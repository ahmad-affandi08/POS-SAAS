<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Data;

use App\Domain\Bersama\Nilai\Uang;

/**
 * PPN masukan satu dokumen pembelian (F-04 fase 1). `tarif` = persen dari `TarifPajak` berlaku (snapshot, string
 * desimal; null = pemasok bukan PKP), pengali DPP pembilang/penyebut, `pajak` = DPP × tarif dibulatkan per dokumen,
 * `dikreditkan` = outlet pembeli PKP (PPN masukan jadi aset); bila tidak, PPN ikut harga landed persediaan (BR-04.2).
 */
final readonly class DataPajakPembelian
{
    public function __construct(
        public ?string $tarif,
        public ?int $pengaliDppPembilang,
        public ?int $pengaliDppPenyebut,
        public Uang $pajak,
        public bool $dikreditkan,
    ) {}

    public static function TanpaPajak(): self
    {
        return new self(null, null, null, Uang::Nol(), false);
    }
}
