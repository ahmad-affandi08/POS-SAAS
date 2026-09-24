<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Kasir\Enum\JenisKategoriKas;

/**
 * Kategori kas masuk/keluar non-penjualan (F-06, PRD v1.34) dengan akun lawan jurnalnya (`IdAkun`, milik
 * Akuntansi). Tidak dihapus; kategori lama dinonaktifkan agar riwayat mutasi tetap bisa dibaca.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Nama
 * @property JenisKategoriKas $Jenis
 * @property int $IdAkun
 * @property bool $Aktif
 * @property int $Urutan
 */
final class KategoriKas extends ModelDasar
{
    use MilikTenant;

    protected $table = 'KategoriKas';

    /** @var array<string, mixed> */
    protected $attributes = ['Aktif' => true, 'Urutan' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Jenis' => JenisKategoriKas::class, 'Aktif' => 'boolean', 'Urutan' => 'integer'];
    }
}
