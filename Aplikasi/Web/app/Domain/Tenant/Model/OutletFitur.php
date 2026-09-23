<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * Modul yang diaktifkan template sektor untuk satu outlet (F-01, BR-01.3, PRD §15.3). Menyimpan pilihan template,
 * bukan hasil efektif: fitur efektif = paket ∩ OutletFitur (`EvaluatorFitur`). Penerapan template hanya menambah,
 * tidak pernah menonaktifkan atau menghapus baris.
 *
 * `Konfigurasi` untuk `pos.retail` = `{"ModeKasir": [...], "ModeKasirDefault": "..."}`.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property string $KunciFitur
 * @property bool $Aktif
 * @property array<string, mixed>|null $Konfigurasi
 */
final class OutletFitur extends ModelDasar
{
    use MilikTenant;

    public const KUNCI_POS = 'pos.retail';

    protected $table = 'OutletFitur';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['Aktif' => true, 'Konfigurasi' => null];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Aktif' => 'boolean', 'Konfigurasi' => 'array'];
    }
}
