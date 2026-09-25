<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Penjualan\Enum\StatusPesananPenjualan;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Pesanan penjualan / pre-order dengan uang muka (F-12 bagian 2). `Uuid` dibuat di perangkat. DP tercatat di
 * `PesananPenjualanPembayaran` dan jurnal J-07.3 (`IdJurnal`); tidak menyentuh stok & pendapatan sampai diambil lewat
 * `Penjualan` yang merujuknya (`IdPenjualan`). `TotalPesanan` = perkiraan total saat dipesan (harga final dihitung saat
 * diambil).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property int $IdPerangkat
 * @property int $IdShift
 * @property int $IdPelanggan
 * @property int $IdPengguna
 * @property string $Nomor
 * @property Carbon $DipesanPada
 * @property Carbon $TanggalBisnis
 * @property Carbon $TanggalAmbil
 * @property string|null $Catatan
 * @property StatusPesananPenjualan $Status
 * @property string $TotalPesanan
 * @property string $UangMuka
 * @property string $UangMukaTerpakai
 * @property string $UangMukaDikembalikan
 * @property string $UangMukaHangus
 * @property int|null $IdJurnal
 * @property Carbon|null $SiapPada
 * @property int|null $IdPenjualan
 * @property Carbon|null $DiambilPada
 * @property Carbon|null $DibatalkanPada
 * @property string|null $AlasanBatal
 * @property int|null $IdPembatal
 * @property int|null $IdJurnalPenyelesaian
 * @property Carbon|null $DibuatPada
 */
final class PesananPenjualan extends ModelDasar
{
    use MilikTenant;

    public const JENIS_DOKUMEN = 'PesananPenjualan';

    protected $table = 'PesananPenjualan';

    /**
     * @return HasMany<PesananPenjualanDetail, $this>
     */
    public function Detail(): HasMany
    {
        return $this->hasMany(PesananPenjualanDetail::class, 'IdPesananPenjualan', 'Id');
    }

    /**
     * @return HasMany<PesananPenjualanPembayaran, $this>
     */
    public function Pembayaran(): HasMany
    {
        return $this->hasMany(PesananPenjualanPembayaran::class, 'IdPesananPenjualan', 'Id');
    }

    /** Uang muka yang masih dipegang (belum dipakai, dikembalikan, atau hangus). */
    public function AmbilSisaUangMuka(): Uang
    {
        return Uang::Dari($this->UangMuka)
            ->Kurangi(Uang::Dari($this->UangMukaTerpakai))
            ->Kurangi(Uang::Dari($this->UangMukaDikembalikan))
            ->Kurangi(Uang::Dari($this->UangMukaHangus));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Status' => StatusPesananPenjualan::class,
            'DipesanPada' => 'datetime',
            'TanggalBisnis' => 'date',
            'TanggalAmbil' => 'date',
            'TotalPesanan' => 'decimal:2',
            'UangMuka' => 'decimal:2',
            'UangMukaTerpakai' => 'decimal:2',
            'UangMukaDikembalikan' => 'decimal:2',
            'UangMukaHangus' => 'decimal:2',
            'SiapPada' => 'datetime',
            'DiambilPada' => 'datetime',
            'DibatalkanPada' => 'datetime',
        ];
    }
}
