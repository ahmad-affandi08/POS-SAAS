<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Master pemasok (F-04 fase 1). `TerminHari` 0 = tunai. `Pkp` = PPN masukan dihitung dari `TarifPajak`. Nonaktif =
 * tidak bisa dipilih di dokumen baru; hapus (soft delete) hanya bila belum pernah dipakai.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Kode
 * @property string $Nama
 * @property string|null $NamaKontak
 * @property string|null $NoHp
 * @property string|null $Email
 * @property string|null $Alamat
 * @property string|null $Npwp
 * @property bool $Pkp
 * @property int $TerminHari
 * @property string|null $NamaBank
 * @property string|null $NomorRekening
 * @property string|null $AtasNamaRekening
 * @property string|null $Catatan
 * @property bool $Aktif
 * @property int|null $DibuatOleh
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property Carbon|null $DihapusPada
 */
final class Pemasok extends ModelDasar
{
    use MilikTenant;
    use SoftDeletes;

    protected $table = 'Pemasok';

    /** @var array<string, mixed> */
    protected $attributes = ['Pkp' => false, 'TerminHari' => 0, 'Aktif' => true];

    public function AmbilLabel(): string
    {
        return "{$this->Kode} {$this->Nama}";
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Pkp' => 'boolean', 'Aktif' => 'boolean', 'TerminHari' => 'integer'];
    }
}
