<?php

declare(strict_types=1);

namespace App\Domain\Promo\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Promo\Enum\StatusVoucher;
use Illuminate\Support\Carbon;

/**
 * Kode voucher sebuah promo wajib voucher (F-16c bagian 2, PRD §15 `Voucher`). `Kode` huruf besar, unik per tenant;
 * `MaksimalPakai` null = berulang tanpa batas; `KedaluwarsaPada` UTC eksklusif (null = mengikuti periode promo).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdPromo
 * @property string $Kode
 * @property int|null $MaksimalPakai
 * @property int $JumlahDipakai
 * @property Carbon|null $KedaluwarsaPada
 * @property StatusVoucher $Status
 * @property Carbon|null $DibuatPada
 */
final class Voucher extends ModelDasar
{
    use MilikTenant;

    protected $table = 'Voucher';

    /** @var array<string, mixed> */
    protected $attributes = ['JumlahDipakai' => 0, 'Status' => 'Aktif'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'MaksimalPakai' => 'integer',
            'JumlahDipakai' => 'integer',
            'KedaluwarsaPada' => 'datetime',
            'Status' => StatusVoucher::class,
        ];
    }

    /** Kode voucher kanonik: tanpa spasi tepi, huruf besar. */
    public static function RapikanKode(string $kode): string
    {
        return strtoupper(trim($kode));
    }

    /** Sisa pemakaian di luar pesanan yang masih berlaku; null = tanpa batas. */
    public function AmbilSisaPakai(): ?int
    {
        return $this->MaksimalPakai === null ? null : max(0, $this->MaksimalPakai - $this->JumlahDipakai);
    }
}
