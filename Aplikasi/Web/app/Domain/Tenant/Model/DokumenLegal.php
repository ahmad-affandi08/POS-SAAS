<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Tenant\Enum\JenisDokumenLegal;
use App\Domain\Tenant\Enum\StatusDokumenLegal;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Satu versi dokumen legal (P-06). Versi terbit tidak pernah diubah atau dihapus (BR-P06.1).
 *
 * @property int $Id
 * @property string $Uuid
 * @property JenisDokumenLegal $Jenis
 * @property int $Versi
 * @property string $Judul
 * @property string $Isi
 * @property string|null $RingkasanPerubahan
 * @property bool $Materiil
 * @property Carbon $BerlakuMulai
 * @property StatusDokumenLegal $Status
 * @property int|null $IdPenggunaPengelolaPenerbit
 * @property Carbon|null $DiterbitkanPada
 */
final class DokumenLegal extends ModelDasar
{
    protected $table = 'DokumenLegal';

    /** @var array<string, mixed> */
    protected $attributes = [
        'RingkasanPerubahan' => null,
        'Materiil' => false,
        'Status' => 'Draf',
        'IdPenggunaPengelolaPenerbit' => null,
        'DiterbitkanPada' => null,
    ];

    protected static function booted(): void
    {
        self::updating(static function (DokumenLegal $dokumen): void {
            if ($dokumen->getOriginal('Status') === StatusDokumenLegal::Terbit) {
                throw new LogicException('Dokumen legal yang sudah terbit tidak boleh diubah (BR-P06.1).');
            }
        });

        self::deleting(static function (DokumenLegal $dokumen): void {
            if ($dokumen->getOriginal('Status') === StatusDokumenLegal::Terbit) {
                throw new LogicException('Dokumen legal yang sudah terbit tidak boleh dihapus (BR-P06.1).');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Jenis' => JenisDokumenLegal::class,
            'Versi' => 'integer',
            'Materiil' => 'boolean',
            'BerlakuMulai' => 'date',
            'Status' => StatusDokumenLegal::class,
            'DiterbitkanPada' => 'datetime',
        ];
    }
}
