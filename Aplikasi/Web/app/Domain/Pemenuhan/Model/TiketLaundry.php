<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pemenuhan\Enum\JenisLayananLaundry;
use App\Domain\Pemenuhan\Enum\StatusLaundry;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Tiket laundry (§9.9, SLS-09): satu per penjualan, `Uuid` = Uuid penjualan, `Nomor` = nomor penjualan. Berat (kg)
 * dan/atau item satuan (`[{Nama, Jumlah}]`). Status hanya lewat `UbahStatus()` dan dicatat di `RiwayatStatusDokumen`.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property int $IdPenjualan
 * @property string $Nomor
 * @property int|null $IdPelanggan
 * @property string $NamaPelanggan
 * @property string|null $NoHp
 * @property JenisLayananLaundry $JenisLayanan
 * @property string|null $Berat
 * @property list<array{Nama: string, Jumlah: int}>|null $Item
 * @property string|null $Parfum
 * @property string|null $Catatan
 * @property StatusLaundry $Status
 * @property Carbon $EstimasiSelesaiPada
 * @property Carbon|null $SiapPada
 * @property Carbon|null $DiambilPada
 * @property int|null $DiambilOleh
 * @property Carbon|null $NotifikasiSiapPada
 * @property Carbon|null $NotifikasiSiapDiprosesPada
 * @property Carbon|null $DibuatPada
 */
final class TiketLaundry extends ModelDasar
{
    use MilikTenant;

    public const JENIS_DOKUMEN = 'TiketLaundry';

    protected $table = 'TiketLaundry';

    /** @var array<string, mixed> */
    protected $attributes = [
        'IdPelanggan' => null,
        'NoHp' => null,
        'Berat' => null,
        'Item' => null,
        'Parfum' => null,
        'Catatan' => null,
        'SiapPada' => null,
        'DiambilPada' => null,
        'DiambilOleh' => null,
        'NotifikasiSiapPada' => null,
        'NotifikasiSiapDiprosesPada' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'JenisLayanan' => JenisLayananLaundry::class,
            'Status' => StatusLaundry::class,
            'Item' => 'array',
            'EstimasiSelesaiPada' => 'datetime',
            'SiapPada' => 'datetime',
            'DiambilPada' => 'datetime',
            'NotifikasiSiapPada' => 'datetime',
            'NotifikasiSiapDiprosesPada' => 'datetime',
        ];
    }

    public function UbahStatus(StatusLaundry $tujuan): void
    {
        if (! $this->Status->BisaBerubahKe($tujuan)) {
            throw new LogicException("Status laundry {$this->Status->value} tidak bisa berubah ke {$tujuan->value}.");
        }

        $this->Status = $tujuan;
    }
}
