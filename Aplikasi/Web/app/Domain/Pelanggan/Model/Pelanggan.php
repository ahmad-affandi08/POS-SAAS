<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pelanggan\Enum\StatusPelanggan;
use Illuminate\Support\Carbon;

/**
 * Pelanggan (F-16a, CRM-01). `NoHp` ternormalisasi (angka, awalan 62) dan unik per tenant.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Nama
 * @property string $NoHp
 * @property string|null $Email
 * @property Carbon|null $TanggalLahir
 * @property string|null $Alamat
 * @property list<string>|null $Tag
 * @property string|null $Catatan
 * @property bool $SetujuPemasaran
 * @property StatusPelanggan $Status
 * @property int|null $IdTier
 * @property bool $TierTetap
 * @property string|null $LimitKredit
 * @property int $TerminHari
 * @property string $SaldoDeposit cache Σ `MutasiDeposit.Jumlah` (F-16d)
 * @property Carbon|null $TierDievaluasiPada
 * @property int|null $DibuatOleh
 * @property int|null $IdPerangkatPembuat
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class Pelanggan extends ModelDasar
{
    use MilikTenant;

    protected $table = 'Pelanggan';

    /** @var array<string, mixed> */
    protected $attributes = ['SetujuPemasaran' => false, 'Status' => 'Aktif', 'TerminHari' => 30, 'SaldoDeposit' => '0.00'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'TanggalLahir' => 'date',
            'Tag' => 'array',
            'SetujuPemasaran' => 'boolean',
            'TierTetap' => 'boolean',
            'TierDievaluasiPada' => 'datetime',
            'LimitKredit' => 'decimal:2',
            'TerminHari' => 'integer',
            'SaldoDeposit' => 'decimal:2',
            'Status' => StatusPelanggan::class,
        ];
    }
}
