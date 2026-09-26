<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tindakan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Contracts\Database\Query\Builder as KontrakBuilder;
use Illuminate\Support\Carbon;

/**
 * Tanda "sudah dicek" dokumen yang perlu ditinjau (D-23 C), satu per (JenisDokumen, UuidDokumen) per tenant.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property string $JenisDokumen
 * @property string $UuidDokumen
 * @property int $IdPengguna
 * @property string|null $Catatan
 * @property Carbon|null $DibuatPada
 */
final class TinjauanDokumen extends ModelDasar
{
    use MilikTenant;

    protected $table = 'TinjauanDokumen';

    protected bool $pakaiUuid = false;

    /**
     * Kecualikan dokumen yang sudah ditandai dicek dari kueri penyedia tindakan. [$tabel] = nama tabel dokumen (kolom
     * `Uuid` & `IdTenant`).
     *
     * @template T of KontrakBuilder
     *
     * @param  T  $kueri
     * @return T
     */
    public static function KecualikanDitinjau(KontrakBuilder $kueri, string $jenisDokumen, string $tabel): KontrakBuilder
    {
        $kueri->whereNotExists(fn ($q) => $q->selectRaw('1')
            ->from('TinjauanDokumen')
            ->whereColumn('TinjauanDokumen.IdTenant', "{$tabel}.IdTenant")
            ->whereColumn('TinjauanDokumen.UuidDokumen', "{$tabel}.Uuid")
            ->where('TinjauanDokumen.JenisDokumen', $jenisDokumen));

        return $kueri;
    }
}
