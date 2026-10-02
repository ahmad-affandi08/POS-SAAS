<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Penjualan\Enum\HasilKunjungan;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Kunjungan salesman ke pelanggan (Modul Salesman bagian 1, §9.7, SLS-11). Dicatat sekali dari HP lewat outbox
 * `Kunjungan.Catat` saat check-out; Uuid dari perangkat. Catatan kejadian lapangan: tidak diedit setelah diterima,
 * kecuali menautkan `IdPesananGrosir` yang masih kosong (pesanan dari kunjungan itu tiba belakangan).
 * `Latitude`/`Longitude` string desimal (DECIMAL(10,7)), tidak pernah float.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property int $IdPelanggan
 * @property int $IdPengguna
 * @property int|null $IdPerangkat
 * @property Carbon $Tanggal
 * @property Carbon $MasukPada
 * @property Carbon|null $KeluarPada
 * @property string|null $Latitude
 * @property string|null $Longitude
 * @property int|null $AkurasiMeter
 * @property HasilKunjungan $Hasil
 * @property string|null $Catatan
 * @property int|null $IdPesananGrosir
 * @property Carbon $DiterimaPada
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class KunjunganSales extends ModelDasar
{
    use MilikTenant;

    protected $table = 'KunjunganSales';

    protected static function booted(): void
    {
        // Satu-satunya perubahan sah: menautkan pesanan yang tiba belakangan (kolom masih kosong).
        self::updating(static function (KunjunganSales $kunjungan): void {
            $berubah = array_diff(array_keys($kunjungan->getDirty()), [self::UPDATED_AT]);

            if ($berubah !== ['IdPesananGrosir'] || $kunjungan->getOriginal('IdPesananGrosir') !== null) {
                throw new LogicException('Kunjungan salesman yang sudah diterima tidak boleh diubah.');
            }
        });

        self::deleting(static function (): void {
            throw new LogicException('Kunjungan salesman tidak boleh dihapus.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Tanggal' => 'date',
            'MasukPada' => 'datetime',
            'KeluarPada' => 'datetime',
            'Latitude' => 'decimal:7',
            'Longitude' => 'decimal:7',
            'AkurasiMeter' => 'integer',
            'Hasil' => HasilKunjungan::class,
            'DiterimaPada' => 'datetime',
        ];
    }
}
