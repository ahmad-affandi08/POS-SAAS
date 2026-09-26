<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;

/**
 * Pengaturan reservasi tenant (F-07 mode service). Tanpa baris = bawaan: reservasi online mati, konfirmasi otomatis,
 * slot tiap 30 menit tanpa jeda, maksimal 30 hari ke depan, paling cepat 60 menit dari sekarang, pengingat H-1 aktif.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property bool $OnlineAktif
 * @property bool $KonfirmasiOtomatis
 * @property int $IntervalSlotMenit
 * @property int $JedaMenit
 * @property int $BatasHariKeDepan
 * @property int $MinimalMenitSebelum
 * @property bool $PengingatAktif
 */
final class PengaturanReservasi extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PengaturanReservasi';

    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = [
        'OnlineAktif' => false,
        'KonfirmasiOtomatis' => true,
        'IntervalSlotMenit' => 30,
        'JedaMenit' => 0,
        'BatasHariKeDepan' => 30,
        'MinimalMenitSebelum' => 60,
        'PengingatAktif' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'OnlineAktif' => 'boolean',
            'KonfirmasiOtomatis' => 'boolean',
            'IntervalSlotMenit' => 'integer',
            'JedaMenit' => 'integer',
            'BatasHariKeDepan' => 'integer',
            'MinimalMenitSebelum' => 'integer',
            'PengingatAktif' => 'boolean',
        ];
    }
}
