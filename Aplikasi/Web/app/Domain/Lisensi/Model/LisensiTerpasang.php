<?php

declare(strict_types=1);

namespace App\Domain\Lisensi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use Illuminate\Support\Carbon;

/**
 * Berkas lisensi yang dipasang di server pembeli (D-35). Data platform (tanpa `MilikTenant`); baris terbaru berlaku.
 * `IsiBerkas` disimpan utuh dan diverifikasi ulang setiap dibaca, jadi mengubah baris ini langsung di basis data tidak
 * menambah batas apa pun.
 *
 * @property int $Id
 * @property string $Nomor
 * @property string $Domain
 * @property string $IsiBerkas
 * @property Carbon $DipasangPada
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class LisensiTerpasang extends ModelDasar
{
    protected $table = 'LisensiTerpasang';

    protected bool $pakaiUuid = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['DipasangPada' => 'datetime'];
    }
}
