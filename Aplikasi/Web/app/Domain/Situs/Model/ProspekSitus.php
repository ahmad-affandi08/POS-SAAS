<?php

declare(strict_types=1);

namespace App\Domain\Situs\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Situs\Enum\JenisProspek;
use App\Domain\Situs\Enum\StatusProspek;
use Illuminate\Support\Carbon;

/**
 * Prospek dari formulir kontak/minta demo situs pemasaran (§13.9 bagian B, data platform). `NoHp` & `Email`
 * terenkripsi dan disembunyikan dari serialisasi; `SidikNoHp`/`SidikIp` = HMAC untuk batas pengiriman tanpa data mentah.
 *
 * @property int $Id
 * @property string $Uuid
 * @property JenisProspek $Jenis
 * @property string $Nama
 * @property string|null $NamaUsaha
 * @property string $NoHp
 * @property string $SidikNoHp
 * @property string|null $Email
 * @property string|null $JenisUsaha
 * @property string|null $Kota
 * @property string|null $Pesan
 * @property string|null $HalamanAsal
 * @property StatusProspek $Status
 * @property string|null $Catatan
 * @property int|null $IdPenggunaPengelolaPenangan
 * @property Carbon|null $DitanganiPada
 * @property Carbon $PersetujuanPada
 * @property string $SidikIp
 * @property Carbon|null $DibuatPada
 */
final class ProspekSitus extends ModelDasar
{
    protected $table = 'ProspekSitus';

    /** @var list<string> */
    protected $hidden = ['NoHp', 'Email', 'SidikNoHp', 'SidikIp'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'NamaUsaha' => null,
        'Email' => null,
        'JenisUsaha' => null,
        'Kota' => null,
        'Pesan' => null,
        'HalamanAsal' => null,
        'Catatan' => null,
        'IdPenggunaPengelolaPenangan' => null,
        'DitanganiPada' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Jenis' => JenisProspek::class,
            'Status' => StatusProspek::class,
            'NoHp' => 'encrypted',
            'Email' => 'encrypted',
            'DitanganiPada' => 'datetime',
            'PersetujuanPada' => 'datetime',
        ];
    }

    /**
     * HMAC stabil untuk membatasi pengiriman per nomor/IP tanpa menyimpan nilai mentah. Kunci tersendiri
     * `situs.KunciSidik` (audit F-21) agar tidak ikut berubah saat APP_KEY dirotasi; kosong = APP_KEY.
     */
    public static function BuatSidik(string $nilai): string
    {
        $kunci = config('situs.KunciSidik');

        return hash_hmac('sha256', $nilai, is_string($kunci) && $kunci !== '' ? $kunci : (string) config('app.key'));
    }
}
