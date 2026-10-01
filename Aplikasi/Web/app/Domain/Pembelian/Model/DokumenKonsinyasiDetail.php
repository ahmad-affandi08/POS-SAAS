<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Model;

use App\Domain\Bersama\Dokumen\Model\JagaDokumenTerposting;
use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Baris dokumen konsinyasi (F-05i): jumlah dalam satuan dasar, harga titip per satuan dasar (Retur: HPP berjalan
 * saat keluar), nilai = jumlah × harga.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdDokumenKonsinyasi
 * @property int $IdProduk
 * @property string $Jumlah
 * @property string $HargaSatuan
 * @property string $Nilai
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class DokumenKonsinyasiDetail extends ModelDasar
{
    use JagaDokumenTerposting;
    use MilikTenant;

    protected $table = 'DokumenKonsinyasiDetail';

    protected bool $pakaiUuid = false;

    /** Nilai baris Retur baru diketahui setelah mutasinya dinilai (HPP berjalan), di transaksi yang sama. */
    public function AmbilKolomBolehBerubah(): array
    {
        return ['HargaSatuan', 'Nilai'];
    }
}
