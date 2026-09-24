<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Data;

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Bersama\Nilai\Uang;

/**
 * Satu baris masukan `PostingJurnal` (DesainF05a C.5). Tepat satu dari `peran`/`idAkun` terisi dan tepat satu sisi
 * > 0; pelanggaran ditolak `PostingJurnal` (`BarisJurnalTidakValid`). `idOutlet` = dimensi outlet (BR-02.4) dan
 * penentu pemetaan akun per outlet.
 */
final readonly class DataBarisJurnal
{
    public function __construct(
        public ?PeranAkun $peran,
        public ?int $idAkun,
        public ?int $idOutlet,
        public Uang $debit,
        public Uang $kredit,
        public ?string $memo = null,
    ) {}

    public static function Debit(PeranAkun $peran, Uang $nilai, ?int $idOutlet = null, ?string $memo = null): self
    {
        return new self($peran, null, $idOutlet, $nilai, Uang::Nol(), $memo);
    }

    public static function Kredit(PeranAkun $peran, Uang $nilai, ?int $idOutlet = null, ?string $memo = null): self
    {
        return new self($peran, null, $idOutlet, Uang::Nol(), $nilai, $memo);
    }

    /** Baris dari nilai bertanda: positif = debit, negatif = kredit (besaran), nol = null (tanpa baris). */
    public static function DariSelisih(PeranAkun $peran, Uang $bertanda, ?int $idOutlet = null): ?self
    {
        if ($bertanda->BernilaiNol()) {
            return null;
        }

        return $bertanda->BernilaiNegatif()
            ? self::Kredit($peran, Uang::Nol()->Kurangi($bertanda), $idOutlet)
            : self::Debit($peran, $bertanda, $idOutlet);
    }
}
