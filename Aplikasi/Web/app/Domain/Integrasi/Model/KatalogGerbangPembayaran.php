<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Integrasi\Enum\PenyediaGerbang;

/**
 * Katalog platform (P-05 v2.06): penyedia gerbang pembayaran yang boleh dipilih tenant. Data platform tanpa
 * `IdTenant`; diubah hanya dari Platform Pengelola. Penyedia tanpa baris = diizinkan (bawaan).
 *
 * @property int $Id
 * @property PenyediaGerbang $Penyedia
 * @property bool $Diizinkan
 */
final class KatalogGerbangPembayaran extends ModelDasar
{
    protected $table = 'KatalogGerbangPembayaran';

    protected bool $pakaiUuid = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Penyedia' => PenyediaGerbang::class,
            'Diizinkan' => 'boolean',
        ];
    }
}
