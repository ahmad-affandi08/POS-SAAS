<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * Penugasan pengguna ke outlet (PRD §15.3). F-02 mengisi `IdPeran` dengan peran utama anggota; peran berbeda
 * per outlet disiapkan skemanya untuk flow POS berikutnya.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property int $IdPengguna
 * @property int $IdPeran
 */
final class OutletPengguna extends ModelDasar
{
    use MilikTenant;

    protected $table = 'OutletPengguna';

    protected bool $pakaiUuid = false;
}
