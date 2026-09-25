<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Persediaan\Enum\StatusStokOpname;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Baris stok opname (F-05b, BR-05.3): satu per produk (per batch untuk produk batch, per nomor seri untuk produk
 * seri). `JumlahSistem` + `IdMutasiSnapshot` = snapshot saldo saat mulai (0 untuk baris tambahan hasil hitung,
 * `DariSnapshot` = false). `JumlahFisik` null = belum dihitung (tidak disesuaikan). Saat disetujui:
 * `MutasiSelamaOpname` = Σ mutasi pasangan/batch/seri sejak snapshot, `Selisih` = fisik − (sistem + mutasi selama
 * opname), `NilaiSelisih` = perubahan nilai persediaan. Baris hanya berubah selama opname aktif.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdStokOpname
 * @property int $Urutan
 * @property int $IdProduk
 * @property string $NamaProduk
 * @property string|null $Sku
 * @property int|null $IdBatchStok
 * @property string|null $NomorBatch
 * @property Carbon|null $TanggalKedaluwarsa
 * @property int|null $IdNomorSeri
 * @property string|null $NomorSeri
 * @property-read string $KunciBaris
 * @property bool $DariSnapshot
 * @property string $JumlahSistem
 * @property int $IdMutasiSnapshot
 * @property string|null $JumlahFisik
 * @property int|null $DihitungOleh
 * @property Carbon|null $DihitungPada
 * @property string|null $MutasiSelamaOpname
 * @property string|null $Selisih
 * @property string|null $NilaiSelisih
 * @property-read StokOpname $StokOpname
 */
final class StokOpnameDetail extends ModelDasar
{
    use MilikTenant;

    protected $table = 'StokOpnameDetail';

    protected bool $pakaiUuid = false;

    /** @var list<string> */
    protected $guarded = ['Id', 'KunciBaris'];

    protected static function booted(): void
    {
        $jaga = function (self $baris): void {
            $nilai = StokOpname::query()->whereKey($baris->IdStokOpname)->sharedLock()->value('Status');
            $status = $nilai instanceof StatusStokOpname ? $nilai : StatusStokOpname::tryFrom((string) $nilai);

            if ($status === null || ! $status->CekAktif()) {
                throw new LogicException('Baris stok opname hanya bisa diubah selama opname masih berlangsung atau ditinjau.');
            }
        };

        self::updating($jaga);
        self::deleting(function (): void {
            throw new LogicException('Baris stok opname tidak pernah dihapus.');
        });
    }

    /**
     * @return BelongsTo<StokOpname, $this>
     */
    public function StokOpname(): BelongsTo
    {
        return $this->belongsTo(StokOpname::class, 'IdStokOpname', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Urutan' => 'integer',
            'DariSnapshot' => 'boolean',
            'JumlahSistem' => 'string',
            'IdMutasiSnapshot' => 'integer',
            'JumlahFisik' => 'string',
            'DihitungPada' => 'datetime',
            'MutasiSelamaOpname' => 'string',
            'Selisih' => 'string',
            'NilaiSelisih' => 'string',
            'TanggalKedaluwarsa' => 'date',
        ];
    }
}
