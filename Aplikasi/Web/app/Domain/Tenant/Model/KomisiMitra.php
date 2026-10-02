<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Tenant\Enum\StatusKomisiMitra;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Komisi mitra dari satu tagihan langganan lunas (P-12, BR-P12.1). Data platform tanpa `MilikTenant`. Nilai tidak
 * berubah setelah dicatat; hanya status (lewat `UbahStatus`), tautan pencairan, dan alasan batal.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdMitra
 * @property int $IdTenant
 * @property int $IdTagihanLangganan
 * @property string $NomorTagihan
 * @property string $DasarKomisi
 * @property string $PersenKomisi
 * @property string $Jumlah
 * @property StatusKomisiMitra $Status
 * @property int|null $IdPencairanKomisi
 * @property string|null $AlasanBatal
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class KomisiMitra extends ModelDasar
{
    protected $table = 'KomisiMitra';

    /** @var array<string, mixed> */
    protected $attributes = ['Status' => 'Tertunda', 'IdPencairanKomisi' => null, 'AlasanBatal' => null];

    public function UbahStatus(StatusKomisiMitra $tujuan): void
    {
        if (! $this->Status->BisaBerubahKe($tujuan)) {
            throw new LogicException("Komisi {$this->Status->value} tidak bisa berubah ke {$tujuan->value}.");
        }

        $this->Status = $tujuan;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Status' => StatusKomisiMitra::class,
            'DasarKomisi' => 'decimal:2',
            'PersenKomisi' => 'decimal:2',
            'Jumlah' => 'decimal:2',
        ];
    }
}
