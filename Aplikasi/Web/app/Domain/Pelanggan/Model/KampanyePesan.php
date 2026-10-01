<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pelanggan\Enum\KanalKampanye;
use App\Domain\Pelanggan\Enum\StatusKampanye;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Kampanye pesan CRM-07 ke pelanggan bersegmen. `Segmen` = saringan penerima (`Rfm`, `UuidTier`, `Tag`,
 * `UlangTahunBulanIni`). Status hanya lewat `UbahStatus()`.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Nama
 * @property KanalKampanye $Kanal
 * @property string|null $Judul
 * @property string $Isi
 * @property array{Rfm?: list<string>, UuidTier?: list<string>, Tag?: list<string>, UlangTahunBulanIni?: bool} $Segmen
 * @property StatusKampanye $Status
 * @property Carbon|null $DijadwalkanPada
 * @property Carbon|null $MulaiPada
 * @property Carbon|null $SelesaiPada
 * @property int $JumlahPenerima
 * @property int $JumlahTerkirim
 * @property int $JumlahGagal
 * @property int $JumlahDilewati
 * @property int|null $DibuatOleh
 * @property int|null $DijalankanOleh
 * @property int|null $DibatalkanOleh
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class KampanyePesan extends ModelDasar
{
    use MilikTenant;

    protected $table = 'KampanyePesan';

    /** @var array<string, mixed> */
    protected $attributes = [
        'Status' => 'Draf',
        'JumlahPenerima' => 0,
        'JumlahTerkirim' => 0,
        'JumlahGagal' => 0,
        'JumlahDilewati' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Kanal' => KanalKampanye::class,
            'Status' => StatusKampanye::class,
            'Segmen' => 'array',
            'DijadwalkanPada' => 'datetime',
            'MulaiPada' => 'datetime',
            'SelesaiPada' => 'datetime',
            'JumlahPenerima' => 'integer',
            'JumlahTerkirim' => 'integer',
            'JumlahGagal' => 'integer',
            'JumlahDilewati' => 'integer',
        ];
    }

    public function UbahStatus(StatusKampanye $tujuan): void
    {
        if (! $this->Status->BisaBerubahKe($tujuan)) {
            throw new LogicException("Status kampanye {$this->Status->value} tidak bisa berubah ke {$tujuan->value}.");
        }

        $this->Status = $tujuan;
    }
}
