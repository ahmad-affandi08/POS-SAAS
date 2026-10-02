<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Support\Carbon;

/**
 * Pencairan komisi bulanan satu mitra (P-12 langkah 5). `PotonganPajak` diisi Keuangan sesuai bukti potong (K24:
 * perlakuan pajak menunggu keputusan pemilik & konsultan pajak). Tidak diubah setelah dicatat.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdMitra
 * @property string $Periode
 * @property string $Total
 * @property string $PotonganPajak
 * @property string $JumlahBersih
 * @property Carbon $DibayarPada
 * @property string|null $Catatan
 * @property int $DibuatOleh
 * @property Carbon|null $DibuatPada
 */
final class PencairanKomisi extends ModelDasar
{
    protected $table = 'PencairanKomisi';

    /** @var array<string, mixed> */
    protected $attributes = ['Catatan' => null];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Total' => 'decimal:2', 'PotonganPajak' => 'decimal:2', 'JumlahBersih' => 'decimal:2', 'DibayarPada' => 'date'];
    }
}
