<?php

declare(strict_types=1);

namespace App\Domain\Referensi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Referensi\Enum\TingkatWilayah;
use App\Domain\Referensi\Enum\ZonaWaktu;

/**
 * Wilayah administratif resmi (provinsi & kabupaten/kota), data master platform (P-02, PRD §15.3).
 * Dibaca tenant (profil outlet, tarif PBJT); diubah hanya lewat Aksi di Domain/Pengelola/Referensi.
 *
 * @property int $Id
 * @property string $Kode
 * @property string $Nama
 * @property TingkatWilayah $Tingkat
 * @property string|null $KodeInduk
 * @property ZonaWaktu $ZonaWaktu
 */
final class Wilayah extends ModelDasar
{
    protected $table = 'Wilayah';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['KodeInduk' => null];

    public function getRouteKeyName(): string
    {
        return 'Kode';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Tingkat' => TingkatWilayah::class, 'ZonaWaktu' => ZonaWaktu::class];
    }
}
