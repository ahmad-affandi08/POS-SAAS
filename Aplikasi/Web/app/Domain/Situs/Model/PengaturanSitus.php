<?php

declare(strict_types=1);

namespace App\Domain\Situs\Model;

use App\Domain\Bersama\Model\ModelDasar;

/**
 * Pengaturan situs pemasaran (D-21): satu baris per `Kunci` (saat ini `Umum`), isi JSON (lihat `PengaturanSitusBawaan`).
 *
 * @property int $Id
 * @property string $Kunci
 * @property array<string, mixed> $Nilai
 * @property int|null $IdPenggunaPengelolaPengubah
 */
final class PengaturanSitus extends ModelDasar
{
    public const KUNCI_UMUM = 'Umum';

    protected $table = 'PengaturanSitus';

    protected bool $pakaiUuid = false;

    protected function casts(): array
    {
        return ['Nilai' => 'array'];
    }
}
