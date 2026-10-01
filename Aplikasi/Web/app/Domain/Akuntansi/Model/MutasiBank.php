<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Model;

use App\Domain\Akuntansi\Enum\StatusMutasiBank;
use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Satu baris rekening koran (FIN-09). `Masuk` = uang masuk rekening (kolom "Kredit"/CR di bank), `Keluar` = uang
 * keluar ("Debit"/DB di bank); tepat satu yang > 0. Dicocokkan ke satu `JurnalDetail` akun yang sama.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdAkun
 * @property int $IdImporMutasiBank
 * @property Carbon $Tanggal
 * @property string $Keterangan
 * @property string $Masuk
 * @property string $Keluar
 * @property string|null $Saldo
 * @property string $SidikBaris
 * @property StatusMutasiBank $Status
 * @property int|null $IdJurnalDetail
 * @property string|null $AlasanAbaikan
 * @property int|null $DiputuskanOleh
 * @property Carbon|null $DiputuskanPada
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class MutasiBank extends ModelDasar
{
    use MilikTenant;

    protected $table = 'MutasiBank';

    /** @var array<string, mixed> */
    protected $attributes = ['Masuk' => '0.00', 'Keluar' => '0.00', 'Saldo' => null, 'IdJurnalDetail' => null, 'AlasanAbaikan' => null];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Tanggal' => 'date', 'Status' => StatusMutasiBank::class, 'DiputuskanPada' => 'datetime'];
    }
}
