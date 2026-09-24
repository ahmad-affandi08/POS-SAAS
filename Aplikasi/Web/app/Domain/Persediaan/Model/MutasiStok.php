<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Persediaan\Enum\JenisMutasi;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Satu baris buku besar stok (BR-05.1, DesainF05a B.2). Append-only: hanya disisipkan `CatatMutasiStok`, tidak
 * pernah diubah atau dihapus (koreksi = baris pembalik ber-`IdMutasiAsal`). Kuantitas, HPP, dan uang = string desimal.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdProduk
 * @property int $IdGudang
 * @property int|null $IdBatchStok
 * @property int|null $IdNomorSeri
 * @property JenisMutasi $JenisMutasi
 * @property string $Jumlah
 * @property string $HppSatuan
 * @property string $TotalHpp
 * @property string $SelisihHpp
 * @property string $SaldoSetelah
 * @property string $NilaiSetelah
 * @property string|null $HppRataRataSetelah
 * @property JenisReferensiMutasi $JenisReferensi
 * @property int $IdReferensi
 * @property int|null $IdReferensiDetail
 * @property string|null $UuidReferensi
 * @property string|null $NomorReferensi
 * @property string $KunciBaris
 * @property int|null $IdMutasiAsal
 * @property Carbon $TanggalBisnis
 * @property int|null $DibuatOleh
 * @property int|null $IdPerangkat
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read BatchStok|null $BatchStok
 * @property-read NomorSeri|null $NomorSeri
 * @property-read MutasiStok|null $MutasiAsal
 */
final class MutasiStok extends ModelDasar
{
    use MilikTenant;

    public const PESAN_TIDAK_BISA_DIUBAH = 'Mutasi stok tidak bisa diubah/dihapus';

    protected $table = 'MutasiStok';

    protected bool $pakaiUuid = false;

    /**
     * @return BelongsTo<BatchStok, $this>
     */
    public function BatchStok(): BelongsTo
    {
        return $this->belongsTo(BatchStok::class, 'IdBatchStok', 'Id');
    }

    /**
     * @return BelongsTo<NomorSeri, $this>
     */
    public function NomorSeri(): BelongsTo
    {
        return $this->belongsTo(NomorSeri::class, 'IdNomorSeri', 'Id');
    }

    /**
     * @return BelongsTo<MutasiStok, $this>
     */
    public function MutasiAsal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'IdMutasiAsal', 'Id');
    }

    protected static function booted(): void
    {
        self::updating(function (): void {
            throw new LogicException(self::PESAN_TIDAK_BISA_DIUBAH);
        });
        self::deleting(function (): void {
            throw new LogicException(self::PESAN_TIDAK_BISA_DIUBAH);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'JenisMutasi' => JenisMutasi::class,
            'Jumlah' => 'string',
            'HppSatuan' => 'string',
            'TotalHpp' => 'string',
            'SelisihHpp' => 'string',
            'SaldoSetelah' => 'string',
            'NilaiSetelah' => 'string',
            'HppRataRataSetelah' => 'string',
            'JenisReferensi' => JenisReferensiMutasi::class,
            'IdReferensi' => 'integer',
            'IdReferensiDetail' => 'integer',
            'TanggalBisnis' => 'date',
        ];
    }
}
