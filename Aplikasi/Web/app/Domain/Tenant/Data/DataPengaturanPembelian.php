<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Data;

use App\Domain\Bersama\Nilai\Uang;
use Brick\Math\BigDecimal;

/**
 * Pengaturan pembelian tingkat tenant dari `Tenant.Pengaturan` (F-04 fase 1, PRD v1.51):
 * - `batasPersetujuanPo`: total PO di atas nilai ini butuh persetujuan `pembelian.po.setujui` oleh orang lain
 *   (bawaan Rp 5.000.000, §19.2).
 * - `toleransiPenerimaanPersen`: penerimaan barang boleh melebihi jumlah PO sampai persen ini (BR-04.1, bawaan 0).
 *   Persen = BigDecimal skala 2 (tidak pernah float).
 */
final readonly class DataPengaturanPembelian
{
    public const BATAS_PERSETUJUAN_PO_BAWAAN = '5000000.00';

    public const TOLERANSI_PENERIMAAN_BAWAAN = '0.00';

    /** Batas atas wajar toleransi penerimaan (persen). */
    public const TOLERANSI_PENERIMAAN_MAKSIMAL = '100.00';

    public BigDecimal $toleransiPenerimaanPersen;

    public function __construct(
        public Uang $batasPersetujuanPo,
        BigDecimal|string|null $toleransiPenerimaanPersen = null,
    ) {
        $this->toleransiPenerimaanPersen = BigDecimal::of($toleransiPenerimaanPersen ?? self::TOLERANSI_PENERIMAAN_BAWAAN)->toScale(2);
    }
}
