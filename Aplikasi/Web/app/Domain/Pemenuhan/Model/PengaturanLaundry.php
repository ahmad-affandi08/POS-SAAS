<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * Pengaturan laundry tenant (§9.9). Tanpa baris = bawaan: mati, reguler 48 jam, express 24 jam, tanpa daftar parfum,
 * notifikasi WhatsApp saat siap aktif, cucian dianggap terlambat diambil setelah 7 hari.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property bool $Aktif
 * @property int $JamReguler
 * @property int $JamExpress
 * @property list<string>|null $Parfum
 * @property bool $NotifikasiSiap
 * @property int $HariBelumDiambil
 */
final class PengaturanLaundry extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PengaturanLaundry';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = [
        'Aktif' => false,
        'JamReguler' => 48,
        'JamExpress' => 24,
        'Parfum' => null,
        'NotifikasiSiap' => true,
        'HariBelumDiambil' => 7,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Aktif' => 'boolean',
            'JamReguler' => 'integer',
            'JamExpress' => 'integer',
            'Parfum' => 'array',
            'NotifikasiSiap' => 'boolean',
            'HariBelumDiambil' => 'integer',
        ];
    }
}
