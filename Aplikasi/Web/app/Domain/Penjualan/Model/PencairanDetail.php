<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu pembayaran penjualan yang dicairkan (F-08, BR-08.4). Nomor & tanggal penjualannya di-snapshot supaya rincian
 * dokumen tetap terbaca tanpa menggabung tabel penjualan.
 *
 * `IdPembayaranAktif` adalah salinan `IdPenjualanPembayaran` yang **menahan klaim** atas pembayaran itu lewat indeks
 * unik per tenant, dan dikosongkan saat pencairannya dibatalkan sehingga pembayarannya bisa dicairkan ulang. Penandanya
 * tidak bisa ditaruh di `PenjualanPembayaran` karena model itu menolak `updating` tanpa syarat (append-only).
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdPencairan
 * @property int $Urutan
 * @property int $IdPenjualanPembayaran
 * @property int $IdPenjualan
 * @property string $NomorPenjualan
 * @property Carbon $TanggalPenjualan
 * @property string $Jumlah
 * @property string|null $RefEksternal
 * @property int|null $IdPembayaranAktif
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read Pencairan $Pencairan
 * @property-read PenjualanPembayaran $PenjualanPembayaran
 */
final class PencairanDetail extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PencairanDetail';

    /** Baris rincian dibaca lewat induknya, jadi tabel ini tidak punya kolom `Uuid`. */
    protected bool $pakaiUuid = false;

    /** @var array<string, mixed> */
    protected $attributes = ['RefEksternal' => null];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['TanggalPenjualan' => 'date', 'Jumlah' => 'decimal:2'];
    }

    public function AmbilJumlah(): Uang
    {
        return Uang::Dari($this->Jumlah);
    }

    /**
     * @return BelongsTo<Pencairan, $this>
     */
    public function Pencairan(): BelongsTo
    {
        return $this->belongsTo(Pencairan::class, 'IdPencairan', 'Id');
    }

    /**
     * @return BelongsTo<PenjualanPembayaran, $this>
     */
    public function PenjualanPembayaran(): BelongsTo
    {
        return $this->belongsTo(PenjualanPembayaran::class, 'IdPenjualanPembayaran', 'Id');
    }
}
