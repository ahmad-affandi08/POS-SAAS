<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Data;

use App\Domain\Pelanggan\Enum\KanalKampanye;

/** Isian kampanye pesan CRM-07 (draf). */
final readonly class DataKampanyePesan
{
    /**
     * @param  array{Rfm?: list<string>, UuidTier?: list<string>, Tag?: list<string>, UlangTahunBulanIni?: bool}  $segmen
     */
    public function __construct(
        public string $nama,
        public KanalKampanye $kanal,
        public ?string $judul,
        public string $isi,
        public array $segmen,
        public int $idPengguna,
    ) {}
}
