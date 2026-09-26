<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Penjualan\Enum\StatusTagihanQris;
use Illuminate\Support\Carbon;

/**
 * Tagihan QRIS dinamis dari gerbang pembayaran aktif (F-08, BR-08.5). Dibuat POS lewat `POST /api/pos/v1/qris`,
 * dilunasi webhook gerbang atau cek status, dipakai sebagai `Referensi` pembayaran `QrisDinamis` di `Penjualan.Buat`.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property int $IdPerangkat
 * @property int $IdMetodePembayaran
 * @property string $NomorPesanan
 * @property string $Penyedia
 * @property string|null $IdReferensi
 * @property string $IsiQr
 * @property bool $HalamanBayar
 * @property string $Jumlah
 * @property string|null $Keterangan
 * @property StatusTagihanQris $Status
 * @property Carbon $KedaluwarsaPada
 * @property Carbon|null $LunasPada
 * @property string|null $JumlahDiterima
 * @property string|null $UuidPenjualan
 * @property Carbon|null $TerakhirDicekPada
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class TagihanQris extends ModelDasar
{
    use MilikTenant;

    public const JENIS_DOKUMEN = 'TagihanQris';

    protected $table = 'TagihanQris';

    /** @var array<string, mixed> */
    protected $attributes = [
        'IdReferensi' => null,
        'HalamanBayar' => false,
        'Keterangan' => null,
        'LunasPada' => null,
        'JumlahDiterima' => null,
        'UuidPenjualan' => null,
        'TerakhirDicekPada' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'HalamanBayar' => 'boolean',
            'Jumlah' => 'decimal:2',
            'JumlahDiterima' => 'decimal:2',
            'Status' => StatusTagihanQris::class,
            'KedaluwarsaPada' => 'datetime',
            'LunasPada' => 'datetime',
            'TerakhirDicekPada' => 'datetime',
        ];
    }

    /**
     * Isi respons POS saat tagihan dibuat (`POST /api/pos/v1/qris`).
     *
     * @return array{Uuid: string, NomorPesanan: string, IsiQr: string, HalamanBayar: bool, KedaluwarsaPada: string, Status: string, Jumlah: string}
     */
    public function KeLarikBuat(): array
    {
        return [
            'Uuid' => $this->Uuid,
            'NomorPesanan' => $this->NomorPesanan,
            'IsiQr' => $this->IsiQr,
            'HalamanBayar' => $this->HalamanBayar,
            'KedaluwarsaPada' => $this->KedaluwarsaPada->utc()->toIso8601ZuluString(),
            'Status' => $this->Status->value,
            'Jumlah' => $this->Jumlah,
        ];
    }

    /**
     * Isi respons POS cek status (`GET /api/pos/v1/qris/{uuid}`).
     *
     * @return array{Uuid: string, Status: string, Jumlah: string, LunasPada: string|null, KedaluwarsaPada: string}
     */
    public function KeLarikStatus(): array
    {
        return [
            'Uuid' => $this->Uuid,
            'Status' => $this->Status->value,
            'Jumlah' => $this->Jumlah,
            'LunasPada' => $this->LunasPada?->utc()->toIso8601ZuluString(),
            'KedaluwarsaPada' => $this->KedaluwarsaPada->utc()->toIso8601ZuluString(),
        ];
    }
}
