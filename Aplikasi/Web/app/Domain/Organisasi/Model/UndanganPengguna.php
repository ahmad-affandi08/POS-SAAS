<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Undangan anggota tenant (F-02 langkah 3). Berlaku 72 jam (konfigurasi) dan sekali pakai; token asli hanya ada
 * di email, yang disimpan hash SHA-256.
 *
 * Sengaja tanpa `MilikTenant` (sama seperti `TenantPengguna`): saat undangan dibuka, penerima belum menjadi
 * anggota sehingga tenant aktif belum ada. Baris hanya dicari lewat hash token, atau disaring `IdTenant`
 * tenant aktif secara eksplisit (Kueri `DaftarUndanganMenunggu`, Aksi F-02).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Email
 * @property string $HashToken
 * @property int $IdPeran
 * @property bool $SemuaOutlet
 * @property list<int>|null $DaftarIdOutlet
 * @property int $IdPenggunaPengundang
 * @property Carbon $BerlakuSampai
 * @property Carbon|null $DiterimaPada
 * @property int|null $IdPenggunaPenerima
 * @property Carbon|null $DibatalkanPada
 * @property-read Pengguna $Pengundang
 */
final class UndanganPengguna extends ModelDasar
{
    protected $table = 'UndanganPengguna';

    /** @var list<string> */
    protected $hidden = ['HashToken'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'SemuaOutlet' => false,
        'DaftarIdOutlet' => null,
        'DiterimaPada' => null,
        'IdPenggunaPenerima' => null,
        'DibatalkanPada' => null,
    ];

    public static function BuatHashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function CekBerlaku(): bool
    {
        return $this->DiterimaPada === null && $this->DibatalkanPada === null && $this->BerlakuSampai->isFuture();
    }

    /**
     * @return BelongsTo<Pengguna, $this>
     */
    public function Pengundang(): BelongsTo
    {
        return $this->belongsTo(Pengguna::class, 'IdPenggunaPengundang', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'SemuaOutlet' => 'boolean',
            'DaftarIdOutlet' => 'array',
            'BerlakuSampai' => 'datetime',
            'DiterimaPada' => 'datetime',
            'DibatalkanPada' => 'datetime',
        ];
    }
}
