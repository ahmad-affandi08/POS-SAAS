<?php

declare(strict_types=1);

namespace App\Domain\Promo\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Promo\Enum\CaraPenerimaanKlaim;
use Illuminate\Support\Carbon;

/**
 * Penerimaan pembayaran klaim promo dari pemasok (F-16c bagian 4b/4e, J-16.5): diterima di kas/bank, atau dipotong
 * dari hutang ke pemasok (`Cara` = `PotongHutang`, dokumen `PembayaranHutang` kompensasi di `IdPembayaranHutang`).
 * Append-only.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdPemasok
 * @property Carbon $Tanggal
 * @property string $Jumlah
 * @property CaraPenerimaanKlaim $Cara
 * @property int|null $IdAkunKasBank
 * @property int|null $IdPembayaranHutang
 * @property string|null $Keterangan
 * @property int|null $IdJurnal
 * @property int|null $DibuatOleh
 */
final class PenerimaanKlaimPemasok extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PenerimaanKlaimPemasok';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Tanggal' => 'date', 'Jumlah' => 'decimal:2', 'Cara' => CaraPenerimaanKlaim::class];
    }
}
