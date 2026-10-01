<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Model;

use App\Domain\Bersama\Dokumen\Model\JagaDokumenTerposting;
use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Alokasi biaya tambahan ke satu baris penerimaan barang (v3.41): `Alokasi` = `KePersediaan` + `KeHpp`.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdBiayaTambahanPembelian
 * @property int $IdPenerimaanBarangDetail
 * @property int $IdProduk
 * @property string $Alokasi
 * @property string $KePersediaan
 * @property string $KeHpp
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class BiayaTambahanPembelianDetail extends ModelDasar
{
    use JagaDokumenTerposting;
    use MilikTenant;

    protected $table = 'BiayaTambahanPembelianDetail';

    protected bool $pakaiUuid = false;

    /**
     * @return list<string>
     */
    public function AmbilKolomBolehBerubah(): array
    {
        return [];
    }
}
