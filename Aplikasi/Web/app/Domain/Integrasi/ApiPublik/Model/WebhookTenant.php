<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Webhook keluar tenant (X7 bagian 2). `Rahasia` = kunci HMAC-SHA256 tanda tangan, tersimpan terenkripsi (APP_KEY) dan
 * tidak pernah dikirim ke frontend setelah ditampilkan sekali saat dibuat.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Nama
 * @property string $Url
 * @property string $Rahasia
 * @property list<string> $Peristiwa
 * @property bool $Aktif
 * @property int $DibuatOleh
 * @property Carbon|null $DibuatPada
 */
final class WebhookTenant extends ModelDasar
{
    use MilikTenant;
    use SoftDeletes;

    protected $table = 'WebhookTenant';

    /** @var list<string> */
    protected $hidden = ['Rahasia'];

    /** @var array<string, mixed> */
    protected $attributes = ['Aktif' => true];

    public function CekBerlangganan(string $peristiwa): bool
    {
        return $this->Aktif && in_array($peristiwa, $this->Peristiwa, true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['Rahasia' => 'encrypted', 'Peristiwa' => 'array', 'Aktif' => 'boolean'];
    }
}
