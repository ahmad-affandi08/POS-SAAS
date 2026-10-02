<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Tenant\Enum\JenisMitra;
use App\Domain\Tenant\Enum\StatusMitra;
use Illuminate\Support\Carbon;

/**
 * Mitra, reseller & referral (P-12). Data platform; dikelola konsol (`Pengelola/Mitra`). `Kode` dipakai di tautan
 * pendaftaran `/daftar?mitra={Kode}`. Nomor HP, NPWP, dan nomor rekening terenkripsi.
 *
 * @property int $Id
 * @property string $Uuid
 * @property string $Kode
 * @property string $Nama
 * @property JenisMitra $Jenis
 * @property StatusMitra $Status
 * @property string|null $Email
 * @property string|null $NoHp
 * @property string|null $Npwp
 * @property string|null $NamaBank
 * @property string|null $NomorRekening
 * @property string|null $NamaPemilikRekening
 * @property string $PersenKomisi
 * @property bool $KomisiBerulang
 * @property string|null $Catatan
 * @property int|null $DibuatOleh
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class Mitra extends ModelDasar
{
    protected $table = 'Mitra';

    /** @var list<string> */
    protected $hidden = ['NoHp', 'Npwp', 'NomorRekening'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'Status' => 'Aktif', 'Email' => null, 'NoHp' => null, 'Npwp' => null, 'NamaBank' => null, 'NomorRekening' => null,
        'NamaPemilikRekening' => null, 'PersenKomisi' => '0.00', 'KomisiBerulang' => false, 'Catatan' => null, 'DibuatOleh' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Jenis' => JenisMitra::class,
            'Status' => StatusMitra::class,
            'NoHp' => 'encrypted',
            'Npwp' => 'encrypted',
            'NomorRekening' => 'encrypted',
            'PersenKomisi' => 'decimal:2',
            'KomisiBerulang' => 'boolean',
        ];
    }
}
