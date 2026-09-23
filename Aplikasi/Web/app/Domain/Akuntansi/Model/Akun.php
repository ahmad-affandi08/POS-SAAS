<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Model;

use App\Domain\Akuntansi\Enum\SaldoNormal;
use App\Domain\Akuntansi\Enum\TipeAkun;
use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * Akun COA tenant (PRD §11.2, §15.3, BR-01.2). Kode unik per tenant. `Sistem` = dibuat dari template sektor; akun
 * yang sudah ada (termasuk yang diganti namanya oleh tenant) tidak pernah diubah oleh penerapan template berikutnya.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Kode
 * @property string $Nama
 * @property TipeAkun $Jenis
 * @property int|null $IdInduk
 * @property bool $Sistem
 * @property int|null $IdOutlet
 * @property SaldoNormal $SaldoNormal
 */
final class Akun extends ModelDasar
{
    use MilikTenant;

    protected $table = 'Akun';

    /** @var array<string, mixed> */
    protected $attributes = ['IdInduk' => null, 'Sistem' => false, 'IdOutlet' => null];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Jenis' => TipeAkun::class, 'SaldoNormal' => SaldoNormal::class, 'Sistem' => 'boolean'];
    }
}
