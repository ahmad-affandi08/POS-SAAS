<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pelanggan\Enum\StatusPenerimaKampanye;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Satu penerima kampanye CRM-07 (potret saat kampanye mulai). `Tujuan` (nomor/email) terenkripsi, tidak pernah
 * diserialisasi atau dicatat di log. Status hanya lewat `UbahStatus()`.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdKampanyePesan
 * @property int $IdPelanggan
 * @property string $Tujuan
 * @property StatusPenerimaKampanye $Status
 * @property string|null $Penyedia
 * @property string|null $IdPesanPenyedia
 * @property string|null $PesanGalat
 * @property int $Percobaan
 * @property Carbon|null $TerkirimPada
 * @property Carbon|null $DibuatPada
 */
final class PenerimaKampanye extends ModelDasar
{
    use MilikTenant;

    public const PANJANG_PESAN_GALAT = 300;

    protected $table = 'PenerimaKampanye';

    protected bool $pakaiUuid = false;

    /** @var list<string> */
    protected $hidden = ['Tujuan'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'Status' => 'Diantrekan',
        'Penyedia' => null,
        'IdPesanPenyedia' => null,
        'PesanGalat' => null,
        'Percobaan' => 0,
        'TerkirimPada' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Status' => StatusPenerimaKampanye::class,
            'Tujuan' => 'encrypted',
            'Percobaan' => 'integer',
            'TerkirimPada' => 'datetime',
        ];
    }

    public function UbahStatus(StatusPenerimaKampanye $tujuan): void
    {
        if (! $this->Status->BisaBerubahKe($tujuan)) {
            throw new LogicException("Status penerima kampanye {$this->Status->value} tidak bisa berubah ke {$tujuan->value}.");
        }

        $this->Status = $tujuan;
    }
}
