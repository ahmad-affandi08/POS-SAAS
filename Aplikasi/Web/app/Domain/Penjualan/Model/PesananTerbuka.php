<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Penjualan\Enum\StatusPesananTerbuka;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Pesanan terbuka / open bill (F-07 mode meja fase 1, PRD "Rincian F-07 mode meja & F-10b fase 1"). `Uuid` dibuat di
 * perangkat. Header (meja, label, jumlah tamu) memakai last-writer-wins menurut `HeaderDiubahPada` perangkat. Tidak
 * menyentuh stok/jurnal; pembayarannya adalah `Penjualan` yang merujuk pesanan ini (`IdPenjualan`).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property int $IdPerangkat
 * @property int|null $IdMeja
 * @property string $Nomor
 * @property string|null $Label
 * @property int $JumlahTamu
 * @property StatusPesananTerbuka $Status
 * @property int $IdPengguna
 * @property Carbon $DibukaPada
 * @property Carbon $HeaderDiubahPada
 * @property int|null $IdPenjualan
 * @property Carbon|null $DitutupPada
 * @property string|null $AlasanBatal
 * @property int|null $IdPembatal
 * @property int|null $IdPenyetujuBatal
 * @property int|null $IdPerangkatKunciBayar
 * @property Carbon|null $KunciBayarSampai
 * @property Carbon|null $DiubahPada
 */
final class PesananTerbuka extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PesananTerbuka';

    /**
     * @return HasMany<PesananTerbukaDetail, $this>
     */
    public function Detail(): HasMany
    {
        return $this->hasMany(PesananTerbukaDetail::class, 'IdPesananTerbuka', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Status' => StatusPesananTerbuka::class,
            'JumlahTamu' => 'integer',
            'DibukaPada' => 'datetime',
            'HeaderDiubahPada' => 'datetime',
            'DitutupPada' => 'datetime',
            'KunciBayarSampai' => 'datetime',
        ];
    }
}
