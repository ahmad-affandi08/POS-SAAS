<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Penanda email pengumuman versi materiil dokumen legal yang sudah terkirim ke seorang Owner (BR-P06.5).
 * Append-only; satu baris per versi per pengguna.
 *
 * @property int $Id
 * @property int $IdDokumenLegal
 * @property int $IdPengguna
 * @property Carbon $DikirimPada
 */
final class PengumumanDokumenLegal extends ModelDasar
{
    public $timestamps = false;

    protected $table = 'PengumumanDokumenLegal';

    protected bool $pakaiUuid = false;

    protected static function booted(): void
    {
        self::updating(static fn () => throw new LogicException('Penanda pengumuman dokumen legal tidak boleh diubah.'));
        self::deleting(static fn () => throw new LogicException('Penanda pengumuman dokumen legal tidak boleh dihapus.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['DikirimPada' => 'datetime'];
    }
}
