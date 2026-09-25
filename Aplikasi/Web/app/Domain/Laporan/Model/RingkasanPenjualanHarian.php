<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;

/**
 * Ringkasan penjualan harian per outlet (F-14a, PRD §15). Tabel turunan: hanya ditulis
 * `BangunUlangRingkasanPenjualanHarian` dari dokumen sumber domain Penjualan dan selalu dapat dihitung ulang.
 *
 * @property int $Id
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property Carbon $TanggalBisnis
 * @property string $Kotor
 * @property string $Diskon
 * @property string $Retur
 * @property string $Bersih
 * @property string $Pajak
 * @property string $BiayaLayanan
 * @property string $Hpp
 * @property int $JumlahTransaksi
 * @property int $JumlahRetur
 * @property int $JumlahVoid
 * @property list<array{IdMetodePembayaran: int, Jenis: string, Nama: string, Jumlah: string}>|null $PerMetodeBayar
 * @property list<array{Kanal: string, Bersih: string, JumlahTransaksi: int}>|null $PerKanal
 * @property Carbon $DihitungPada
 */
final class RingkasanPenjualanHarian extends ModelDasar
{
    use MilikTenant;

    protected $table = 'RingkasanPenjualanHarian';

    protected bool $pakaiUuid = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'TanggalBisnis' => 'date',
            'Kotor' => 'decimal:2',
            'Diskon' => 'decimal:2',
            'Retur' => 'decimal:2',
            'Bersih' => 'decimal:2',
            'Pajak' => 'decimal:2',
            'BiayaLayanan' => 'decimal:2',
            'Hpp' => 'decimal:2',
            'JumlahTransaksi' => 'integer',
            'JumlahRetur' => 'integer',
            'JumlahVoid' => 'integer',
            'PerMetodeBayar' => 'array',
            'PerKanal' => 'array',
            'DihitungPada' => 'datetime',
        ];
    }
}
