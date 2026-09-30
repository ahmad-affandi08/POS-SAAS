<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * @property int $Id
 * @property int $IdTenant
 * @property bool $Aktif
 * @property bool $BayarSaatAmbilAktif
 * @property bool $CodAktif
 * @property bool $QrisAktif
 * @property string $MinimalPesanan
 * @property int $MenitKedaluwarsa
 * @property string|null $PesanTutup
 */
final class PengaturanTokoOnline extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PengaturanTokoOnline';

    protected $attributes = [
        'Aktif' => false,
        'BayarSaatAmbilAktif' => true,
        'CodAktif' => true,
        'QrisAktif' => false,
        'MinimalPesanan' => '0.00',
        'MenitKedaluwarsa' => 120,
        'PesanTutup' => null,
    ];

    protected function casts(): array
    {
        return [
            'Aktif' => 'boolean',
            'BayarSaatAmbilAktif' => 'boolean',
            'CodAktif' => 'boolean',
            'QrisAktif' => 'boolean',
            'MinimalPesanan' => 'decimal:2',
            'MenitKedaluwarsa' => 'integer',
        ];
    }
}
