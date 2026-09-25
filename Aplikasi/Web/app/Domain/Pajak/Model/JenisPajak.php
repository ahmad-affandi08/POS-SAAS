<?php

declare(strict_types=1);

namespace App\Domain\Pajak\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Pajak\Enum\CakupanPajak;
use App\Domain\Pajak\Enum\KategoriJenisPajak;

/**
 * Jenis pajak (PRD §12.1, §15.3), misal Ppn (nasional) dan PbjtMakananMinuman (daerah). `Kategori` (PRD v1.46) =
 * kategori untuk syarat profil pajak outlet & akun jurnal; kode tidak pernah dibaca sebagai string tetap.
 *
 * @property int $Id
 * @property string $Kode
 * @property string $Nama
 * @property CakupanPajak $Cakupan
 * @property KategoriJenisPajak $Kategori
 */
final class JenisPajak extends ModelDasar
{
    protected $table = 'JenisPajak';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['Kategori' => 'Lainnya'];

    public function getRouteKeyName(): string
    {
        return 'Kode';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Cakupan' => CakupanPajak::class, 'Kategori' => KategoriJenisPajak::class];
    }
}
