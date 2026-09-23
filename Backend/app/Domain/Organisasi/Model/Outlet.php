<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * Outlet (PRD §15.3). F-00 membuat "Outlet Utama"; kota, profil pajak, dan template sektor diisi F-01/F-02.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdMerek
 * @property string $Kode
 * @property string $Nama
 * @property string|null $Alamat
 * @property string|null $KodeKota
 * @property string $ZonaWaktu
 * @property string|null $TemplateSektor
 * @property string $JamTutupBuku
 * @property array<string, mixed>|null $ProfilPajak
 */
final class Outlet extends ModelDasar
{
    use MilikTenant;

    protected $table = 'Outlet';

    /** @var array<string, mixed> */
    protected $attributes = [
        'Alamat' => null,
        'KodeKota' => null,
        'ZonaWaktu' => 'Asia/Jakarta',
        'TemplateSektor' => null,
        'JamTutupBuku' => '04:00',
        'ProfilPajak' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['ProfilPajak' => 'array'];
    }
}
