<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Pengelola\Integrasi\Enum\JenisIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\LingkunganIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\PenyediaIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\StatusIntegrasi;
use Illuminate\Support\Carbon;

/**
 * Konfigurasi satu integrasi platform untuk satu lingkungan (P-05). `Kredensial` terenkripsi dan disembunyikan dari
 * serialisasi; tampilan hanya memakai `PetunjukKredensial` (BR-P05.1, BR-P05.6).
 *
 * @property int $Id
 * @property string $Uuid
 * @property JenisIntegrasi $Jenis
 * @property LingkunganIntegrasi $Lingkungan
 * @property PenyediaIntegrasi $Penyedia
 * @property array<string, string|int> $Pengaturan
 * @property array<string, string> $Kredensial
 * @property array<string, string> $PetunjukKredensial
 * @property bool $Aktif
 * @property StatusIntegrasi $Status
 * @property Carbon|null $TerakhirDiujiPada
 * @property array{Berhasil: bool, Pesan: string, DurasiMs: int}|null $HasilUji
 * @property int $GagalBeruntun
 * @property Carbon $KredensialDiubahPada
 * @property int $RotasiSetiapHari
 * @property Carbon|null $DiubahPada
 */
final class KonfigurasiIntegrasi extends ModelDasar
{
    protected $table = 'KonfigurasiIntegrasi';

    /** @var list<string> */
    protected $hidden = ['Kredensial'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'Aktif' => false,
        'Status' => 'BelumDiuji',
        'TerakhirDiujiPada' => null,
        'HasilUji' => null,
        'GagalBeruntun' => 0,
        'RotasiSetiapHari' => 90,
    ];

    public function CekPerluRotasi(): bool
    {
        return $this->KredensialDiubahPada->copy()->addDays($this->RotasiSetiapHari)->isPast();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Jenis' => JenisIntegrasi::class,
            'Lingkungan' => LingkunganIntegrasi::class,
            'Penyedia' => PenyediaIntegrasi::class,
            'Pengaturan' => 'array',
            'Kredensial' => 'encrypted:array',
            'PetunjukKredensial' => 'array',
            'Aktif' => 'boolean',
            'Status' => StatusIntegrasi::class,
            'TerakhirDiujiPada' => 'datetime',
            'HasilUji' => 'array',
            'GagalBeruntun' => 'integer',
            'KredensialDiubahPada' => 'datetime',
            'RotasiSetiapHari' => 'integer',
        ];
    }
}
