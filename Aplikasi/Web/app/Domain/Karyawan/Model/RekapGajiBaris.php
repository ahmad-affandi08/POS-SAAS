<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Satu karyawan di rekap gaji (F-18 bagian 3). Bersih = GajiPokok + Komisi + Tambahan − PotonganKasbon − PotonganLain.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdRekapGaji
 * @property int $IdKaryawan
 * @property string $GajiPokok
 * @property string $Komisi
 * @property string $Tambahan
 * @property string $PotonganKasbon
 * @property string $PotonganLain
 * @property string $Bersih
 * @property string|null $Catatan
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class RekapGajiBaris extends ModelDasar
{
    use MilikTenant;

    protected $table = 'RekapGajiBaris';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['Catatan' => null];

    public function HitungKotor(): Uang
    {
        return Uang::Dari($this->GajiPokok)->Tambah(Uang::Dari($this->Komisi))->Tambah(Uang::Dari($this->Tambahan));
    }

    public function HitungPotongan(): Uang
    {
        return Uang::Dari($this->PotonganKasbon)->Tambah(Uang::Dari($this->PotonganLain));
    }
}
