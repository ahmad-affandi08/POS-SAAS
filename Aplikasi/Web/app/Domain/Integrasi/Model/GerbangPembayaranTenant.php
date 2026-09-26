<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Integrasi\Enum\LingkunganGerbang;
use App\Domain\Integrasi\Enum\PenyediaGerbang;
use App\Domain\Integrasi\Enum\StatusUjiGerbang;
use Illuminate\Support\Carbon;

/**
 * Gerbang pembayaran QRIS dinamis milik tenant (F-08, P-05 v2.06): akun merchant tenant sendiri, satu per tenant.
 * `Kredensial` terenkripsi dan disembunyikan dari serialisasi; tampilan hanya memakai `PetunjukKredensial`
 * (BR-P05.1). Pengelola platform tidak pernah membaca kolom ini.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property PenyediaGerbang $Penyedia
 * @property LingkunganGerbang $Lingkungan
 * @property array<string, string|int> $Pengaturan
 * @property array<string, string> $Kredensial
 * @property array<string, string> $PetunjukKredensial
 * @property StatusUjiGerbang $StatusUji
 * @property string|null $PesanUji
 * @property Carbon|null $DiujiPada
 * @property bool $Aktif
 * @property string $TokenWebhook
 * @property Carbon|null $WebhookDiterimaPada
 * @property Carbon|null $WebhookDitolakPada
 * @property Carbon|null $DiubahPada
 */
final class GerbangPembayaranTenant extends ModelDasar
{
    use MilikTenant;

    protected $table = 'GerbangPembayaranTenant';

    /** @var list<string> */
    protected $hidden = ['Kredensial', 'TokenWebhook'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'StatusUji' => 'BelumDiuji',
        'Aktif' => false,
    ];

    /**
     * Pengaturan untuk adaptor: pengaturan penyedia + `Mode` dari lingkungan.
     *
     * @return array<string, string|int>
     */
    public function AmbilPengaturanAdaptor(): array
    {
        return [...$this->Pengaturan, 'Mode' => $this->Lingkungan->value];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Penyedia' => PenyediaGerbang::class,
            'Lingkungan' => LingkunganGerbang::class,
            'Pengaturan' => 'array',
            'Kredensial' => 'encrypted:array',
            'PetunjukKredensial' => 'array',
            'StatusUji' => StatusUjiGerbang::class,
            'DiujiPada' => 'datetime',
            'Aktif' => 'boolean',
            'WebhookDiterimaPada' => 'datetime',
            'WebhookDitolakPada' => 'datetime',
        ];
    }
}
