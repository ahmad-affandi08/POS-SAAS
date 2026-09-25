<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Tutup harian (End of Day) satu outlet untuk satu tanggal bisnis (F-15). Ditulis `TutupHarianOutlet`; cuplikan
 * `JumlahTransaksi` & `PenjualanBersih` diambil dari `RingkasanPenjualanHarian` saat ditutup.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property Carbon $TanggalBisnis
 * @property Carbon $DitutupPada
 * @property int $DitutupOleh
 * @property int $JumlahTransaksi
 * @property string $PenjualanBersih
 * @property list<array{Kode: string, Pesan: string}>|null $Peringatan
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class TutupHarian extends ModelDasar
{
    use MilikTenant;

    protected $table = 'TutupHarian';

    protected bool $pakaiUuid = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'TanggalBisnis' => 'date',
            'DitutupPada' => 'datetime',
            'JumlahTransaksi' => 'integer',
            'Peringatan' => 'array',
        ];
    }
}
