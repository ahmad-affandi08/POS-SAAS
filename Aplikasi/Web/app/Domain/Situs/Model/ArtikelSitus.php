<?php

declare(strict_types=1);

namespace App\Domain\Situs\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Situs\Enum\StatusArtikel;
use Illuminate\Support\Carbon;

/**
 * Artikel blog situs pemasaran (§13.9 bagian B2, data platform). `Isi` memakai format teks ringan situs (tanpa HTML).
 * Tampil publik di `/blog/{Slug}` hanya bila `Terbit`; `DiterbitkanPada` diisi saat pertama terbit dan dipakai untuk
 * urutan, tanggal artikel, dan peta situs.
 *
 * @property int $Id
 * @property string $Uuid
 * @property string $Slug
 * @property string $Judul
 * @property string|null $Ringkasan
 * @property string $Isi
 * @property string|null $Kategori
 * @property string|null $NamaPenulis
 * @property string|null $UuidGambarSampul
 * @property string|null $JudulSeo
 * @property string|null $DeskripsiSeo
 * @property StatusArtikel $Status
 * @property Carbon|null $DiterbitkanPada
 * @property int|null $IdPenggunaPengelolaPengubah
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class ArtikelSitus extends ModelDasar
{
    /** Slug artikel: satu segmen huruf kecil, angka, tanda hubung. */
    public const POLA_SLUG = '[a-z0-9]+(?:-[a-z0-9]+)*';

    protected $table = 'ArtikelSitus';

    /** @var array<string, mixed> */
    protected $attributes = [
        'Status' => 'Draf',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Status' => StatusArtikel::class,
            'DiterbitkanPada' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'Uuid';
    }

    public function CekTerbit(): bool
    {
        return $this->Status === StatusArtikel::Terbit;
    }
}
