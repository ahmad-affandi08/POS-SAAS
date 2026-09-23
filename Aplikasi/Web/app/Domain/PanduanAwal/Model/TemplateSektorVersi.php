<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\PanduanAwal\Enum\StatusTemplateSektor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Satu versi isi template sektor (P-03, BR-P03.3, BR-P03.4). Versi terbit/usang tidak diubah dan tidak dihapus;
 * satu-satunya perubahan yang diizinkan adalah Terbit → Usang saat versi penggantinya terbit.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTemplateSektor
 * @property int $Versi
 * @property StatusTemplateSektor $Status
 * @property array<string, mixed> $Isi
 * @property array{Lolos: bool, Galat: list<array{Bagian: string, Pesan: string}>}|null $HasilValidasi
 * @property Carbon|null $DivalidasiPada
 * @property int|null $IdVersiAsal
 * @property int|null $IdPenggunaPengelolaPenerbit
 * @property Carbon|null $DiterbitkanPada
 * @property Carbon|null $DiusangkanPada
 * @property-read TemplateSektor $TemplateSektor
 */
final class TemplateSektorVersi extends ModelDasar
{
    protected $table = 'TemplateSektorVersi';

    /** @var array<string, mixed> */
    protected $attributes = [
        'Status' => 'Draf',
        'HasilValidasi' => null,
        'DivalidasiPada' => null,
        'IdVersiAsal' => null,
        'IdPenggunaPengelolaPenerbit' => null,
        'DiterbitkanPada' => null,
        'DiusangkanPada' => null,
    ];

    protected static function booted(): void
    {
        self::updating(static function (TemplateSektorVersi $versi): void {
            $statusLama = $versi->getOriginal('Status');

            if ($statusLama === StatusTemplateSektor::Draf) {
                return;
            }

            // Satu-satunya perubahan yang sah: Terbit → Usang beserta waktu pengusangannya.
            $kolomBerubah = array_diff(array_keys($versi->getDirty()), ['Status', 'DiusangkanPada', self::UPDATED_AT]);
            $diusangkan = $statusLama === StatusTemplateSektor::Terbit && $versi->Status === StatusTemplateSektor::Usang;

            if ($kolomBerubah !== [] || ! $diusangkan) {
                throw new LogicException('Versi template yang sudah terbit tidak boleh diubah (BR-P03.4).');
            }
        });

        self::deleting(static function (TemplateSektorVersi $versi): void {
            if ($versi->getOriginal('Status') !== StatusTemplateSektor::Draf) {
                throw new LogicException('Hanya draf template yang boleh dihapus (BR-P03.2, BR-P03.4).');
            }
        });
    }

    /**
     * @return BelongsTo<TemplateSektor, $this>
     */
    public function TemplateSektor(): BelongsTo
    {
        return $this->belongsTo(TemplateSektor::class, 'IdTemplateSektor', 'Id');
    }

    public function CekLolosValidasi(): bool
    {
        return ($this->HasilValidasi['Lolos'] ?? false) === true;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Versi' => 'integer',
            'Status' => StatusTemplateSektor::class,
            'Isi' => 'array',
            'HasilValidasi' => 'array',
            'DivalidasiPada' => 'datetime',
            'DiterbitkanPada' => 'datetime',
            'DiusangkanPada' => 'datetime',
        ];
    }
}
