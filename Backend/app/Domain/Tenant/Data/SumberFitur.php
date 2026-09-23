<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Data;

/**
 * Masukan EvaluatorFitur (P-04). Dirakit dari Langganan tenant di F-00/F-19; di P-04 diuji murni.
 */
final readonly class SumberFitur
{
    /**
     * @param  list<string>  $fiturPaket  kunci fitur paket langganan
     * @param  array<string, int|null>  $batasPaket  kolom Paket::KOLOM_BATAS, null = tak terbatas
     * @param  list<array{KunciFitur: string|null, TambahanBatas: array<string, int>|null, Jumlah: int}>  $addon  add-on aktif
     * @param  list<string>  $overrideFitur  fitur yang diberikan override pengelola aktif (P-07)
     * @param  array<string, int|null>  $overrideBatas  batas yang ditimpa override pengelola aktif (P-07)
     * @param  array<string, bool>  $flagFitur  flag fitur (P-10); kunci tidak ada = diizinkan, false = dimatikan
     * @param  list<string>|null  $modulOutletAktif  fitur yang diaktifkan outlet/template (OutletFitur); null = tanpa batasan
     */
    public function __construct(
        public array $fiturPaket,
        public array $batasPaket,
        public array $addon = [],
        public array $overrideFitur = [],
        public array $overrideBatas = [],
        public array $flagFitur = [],
        public ?array $modulOutletAktif = null,
    ) {}
}
