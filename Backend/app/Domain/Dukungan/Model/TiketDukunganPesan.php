<?php

declare(strict_types=1);

namespace App\Domain\Dukungan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Dukungan\Enum\JenisPengirimPesan;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu pesan dalam percakapan tiket (P-09). Append-only. `CatatanInternal` hanya terlihat oleh tim internal.
 * Lampiran disimpan di disk privat; kolom `Lampiran` hanya berisi metadata.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdTiketDukungan
 * @property JenisPengirimPesan $JenisPengirim
 * @property int|null $IdPengguna
 * @property int|null $IdPenggunaPengelola
 * @property string|null $NamaPengirim
 * @property bool $CatatanInternal
 * @property string $Isi
 * @property list<array{Uuid: string, NamaAsli: string, Mime: string, UkuranByte: int, Path: string}>|null $Lampiran
 * @property Carbon $DibuatPada
 * @property-read TiketDukungan $TiketDukungan
 */
final class TiketDukunganPesan extends ModelDasar
{
    use MilikTenant;

    protected $table = 'TiketDukunganPesan';

    /** @var array<string, mixed> */
    protected $attributes = [
        'IdPengguna' => null,
        'IdPenggunaPengelola' => null,
        'NamaPengirim' => null,
        'CatatanInternal' => false,
        'Lampiran' => null,
    ];

    /**
     * @return BelongsTo<TiketDukungan, $this>
     */
    public function TiketDukungan(): BelongsTo
    {
        return $this->belongsTo(TiketDukungan::class, 'IdTiketDukungan', 'Id');
    }

    /**
     * @return array{Uuid: string, NamaAsli: string, Mime: string, UkuranByte: int, Path: string}|null
     */
    public function CariLampiran(string $uuid): ?array
    {
        foreach ($this->Lampiran ?? [] as $lampiran) {
            if ($lampiran['Uuid'] === $uuid) {
                return $lampiran;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'JenisPengirim' => JenisPengirimPesan::class,
            'CatatanInternal' => 'boolean',
            'Lampiran' => 'array',
        ];
    }
}
