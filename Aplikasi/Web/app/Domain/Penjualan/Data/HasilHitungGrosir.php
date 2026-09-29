<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Uang;

/**
 * Hasil hitung dokumen grosir (F-12, §9.7): angka dokumen + snapshot tarif PPN & pengali DPP yang dipakai, agar
 * total tidak bergeser sendiri saat `TarifPajak` baru terbit.
 *
 * `tarifPpn` null = outlet bukan PKP atau tarif PPN belum terbit, sehingga PPN tidak dihitung (CLAUDE.md #12: tarif
 * tidak pernah di-hard-code, dan tanpa tarif berlaku pajaknya memang tidak dipungut).
 */
final readonly class HasilHitungGrosir
{
    /**
     * @param  array<string, Uang>  $rincianPajak  kode jenis pajak → jumlah pajak dokumen (untuk baris jurnalnya)
     */
    public function __construct(
        public Uang $subtotal,
        public Uang $diskon,
        public Uang $dasarPengenaanPajak,
        public Uang $pajak,
        public Uang $total,
        public ?string $tarifPpn = null,
        public ?int $pengaliDppPembilang = null,
        public ?int $pengaliDppPenyebut = null,
        public array $rincianPajak = [],
    ) {}
}
