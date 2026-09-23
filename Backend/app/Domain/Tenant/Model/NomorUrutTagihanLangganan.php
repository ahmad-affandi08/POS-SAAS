<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;

/**
 * Penghitung nomor tagihan langganan per tahun (BR-P08.1). Hanya diubah `PenomorTagihanLangganan` di dalam transaksi
 * pembuatan tagihan dengan kunci baris, sehingga nomor urut tanpa celah.
 *
 * @property int $Id
 * @property int $Tahun
 * @property int $NomorTerakhir
 */
final class NomorUrutTagihanLangganan extends ModelDasar
{
    protected $table = 'NomorUrutTagihanLangganan';

    protected bool $pakaiUuid = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Tahun' => 'integer', 'NomorTerakhir' => 'integer'];
    }
}
