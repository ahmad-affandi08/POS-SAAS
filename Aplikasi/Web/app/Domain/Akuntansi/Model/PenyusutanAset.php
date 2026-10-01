<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Penyusutan satu aset tetap untuk satu bulan (FIN-10): unik per (aset, periode), jadi penyusutan terjadwal yang
 * berjalan dua kali tidak menjurnal dua kali. Jurnal: Dr Beban Penyusutan / Cr Akumulasi Penyusutan.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdAsetTetap
 * @property string $Periode
 * @property string $Jumlah
 * @property int|null $IdJurnal
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class PenyusutanAset extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PenyusutanAset';

    /** @var array<string, mixed> */
    protected $attributes = ['IdJurnal' => null];
}
