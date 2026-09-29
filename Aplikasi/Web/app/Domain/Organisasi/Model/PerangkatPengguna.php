<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Support\Carbon;

/**
 * Satu pemasangan Aplikasi Owner milik akun. Bukan MilikTenant karena satu token pengguna melayani seluruh tenant
 * yang boleh ia akses; semua kueri wajib disaring `IdPengguna` penerima atau hash tokennya sendiri.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdPengguna
 * @property int|null $IdTokenAksesPengguna
 * @property string $Nama
 * @property string $Platform
 * @property string $Token
 * @property string $HashToken
 * @property bool $Aktif
 * @property Carbon $TerakhirTerdaftarPada
 */
final class PerangkatPengguna extends ModelDasar
{
    protected $table = 'PerangkatPengguna';

    protected $hidden = ['Token', 'HashToken'];

    protected $attributes = ['Aktif' => true, 'IdTokenAksesPengguna' => null];

    public static function BuatHashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'Token' => 'encrypted',
            'Aktif' => 'boolean',
            'TerakhirTerdaftarPada' => 'datetime',
        ];
    }
}
