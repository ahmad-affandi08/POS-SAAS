<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * Kategori produk tenant (PRD §15.3). F-01 membuat kategori akar dari template sektor; F-03 mengelola sub-kategori
 * dan menambahkan `IdStasiunDapur` (F-10).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int|null $IdInduk
 * @property string $Nama
 * @property int $Urutan
 */
final class Kategori extends ModelDasar
{
    use MilikTenant;

    protected $table = 'Kategori';

    /** @var array<string, mixed> */
    protected $attributes = ['IdInduk' => null, 'Urutan' => 0];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Urutan' => 'integer'];
    }
}
