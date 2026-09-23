<?php

declare(strict_types=1);

namespace App\Domain\Referensi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Referensi\Enum\JenisReferensiBank;

/**
 * Referensi pembayaran: bank, dompet digital, jaringan EDC, penerbit QRIS (P-02, PRD §15.3).
 *
 * @property int $Id
 * @property string $Kode
 * @property string $Nama
 * @property JenisReferensiBank $Jenis
 * @property bool $Aktif
 */
final class ReferensiBank extends ModelDasar
{
    protected $table = 'ReferensiBank';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['Aktif' => true];

    public function getRouteKeyName(): string
    {
        return 'Kode';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Jenis' => JenisReferensiBank::class, 'Aktif' => 'boolean'];
    }
}
