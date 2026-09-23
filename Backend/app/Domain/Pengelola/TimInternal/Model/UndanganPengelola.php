<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Model;

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Undangan anggota tim internal (P-01 langkah 3, PRD §15.3). Sekali pakai, berlaku 48 jam.
 *
 * @property int $Id
 * @property string $Uuid
 * @property string $Email
 * @property string $HashToken
 * @property list<string> $KodePeran
 * @property int $IdPenggunaPengelolaPengundang
 * @property Carbon $BerlakuSampai
 * @property Carbon|null $DiterimaPada
 * @property Carbon|null $DibatalkanPada
 * @property Carbon|null $DibuatPada
 */
final class UndanganPengelola extends ModelDasar
{
    protected $table = 'UndanganPengelola';

    /** @var list<string> */
    protected $hidden = ['HashToken'];

    /**
     * Nilai bawaan kolom, sama dengan default migrasi.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'DiterimaPada' => null,
        'DibatalkanPada' => null,
    ];

    public static function HashDariToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * @return BelongsTo<PenggunaPengelola, $this>
     */
    public function Pengundang(): BelongsTo
    {
        return $this->belongsTo(PenggunaPengelola::class, 'IdPenggunaPengelolaPengundang', 'Id');
    }

    public function MasihBerlaku(): bool
    {
        return $this->DiterimaPada === null
            && $this->DibatalkanPada === null
            && $this->BerlakuSampai->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'KodePeran' => 'array',
            'BerlakuSampai' => 'datetime',
            'DiterimaPada' => 'datetime',
            'DibatalkanPada' => 'datetime',
        ];
    }
}
