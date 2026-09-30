<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pemenuhan\Enum\StatusPengirimanPesanan;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdPesananOnline
 * @property int $IdOutlet
 * @property int|null $IdKurir
 * @property string|null $NamaPenyedia
 * @property string|null $NomorResi
 * @property StatusPengirimanPesanan $Status
 * @property Carbon|null $PerkiraanTibaPada
 * @property Carbon|null $DikemasPada
 * @property Carbon|null $DikirimPada
 * @property Carbon|null $DiterimaPada
 * @property string|null $NamaPenerima
 * @property string|null $PathBukti
 * @property string|null $Alasan
 * @property int|null $DiubahOleh
 */
final class PengirimanPesanan extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PengirimanPesanan';

    protected $attributes = [
        'IdKurir' => null, 'NamaPenyedia' => null, 'NomorResi' => null, 'PerkiraanTibaPada' => null,
        'DikemasPada' => null, 'DikirimPada' => null, 'DiterimaPada' => null, 'NamaPenerima' => null,
        'PathBukti' => null, 'Alasan' => null, 'DiubahOleh' => null,
    ];

    public function UbahStatus(StatusPengirimanPesanan $tujuan): void
    {
        if (! $this->Status->BisaBerubahKe($tujuan)) {
            throw new LogicException("Status pengiriman {$this->Status->value} tidak bisa berubah ke {$tujuan->value}.");
        }

        $this->Status = $tujuan;
    }

    protected function casts(): array
    {
        return [
            'Status' => StatusPengirimanPesanan::class, 'PerkiraanTibaPada' => 'datetime', 'DikemasPada' => 'datetime',
            'DikirimPada' => 'datetime', 'DiterimaPada' => 'datetime',
        ];
    }
}
