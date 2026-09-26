<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Penjualan\Enum\JenisPesanKeluar;
use App\Domain\Penjualan\Enum\KanalPesanKeluar;
use App\Domain\Penjualan\Enum\StatusPesanKeluar;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Pesan keluar ke pelanggan (K3): struk digital lewat WhatsApp atau email, diminta dari POS dan dikirim tugas antrean
 * `KirimStrukDigitalTugas`. `Uuid` dari perangkat (idempoten). `Tujuan` (nomor/email pelanggan) terenkripsi, tidak
 * pernah diserialisasi atau dicatat di log. Status hanya lewat `UbahStatus()` (`StatusPesanKeluar::BisaBerubahKe`).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property int|null $IdPerangkat
 * @property KanalPesanKeluar $Kanal
 * @property JenisPesanKeluar $Jenis
 * @property int $IdReferensi
 * @property string $Tujuan
 * @property string|null $Penyedia
 * @property StatusPesanKeluar $Status
 * @property string|null $IdPesanPenyedia
 * @property string|null $PesanGalat
 * @property int $Percobaan
 * @property Carbon|null $TerkirimPada
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class PesanKeluar extends ModelDasar
{
    use MilikTenant;

    public const PANJANG_PESAN_GALAT = 300;

    protected $table = 'PesanKeluar';

    /** @var list<string> */
    protected $hidden = ['Tujuan'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'IdPerangkat' => null,
        'Penyedia' => null,
        'IdPesanPenyedia' => null,
        'PesanGalat' => null,
        'Percobaan' => 0,
        'TerkirimPada' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Kanal' => KanalPesanKeluar::class,
            'Jenis' => JenisPesanKeluar::class,
            'Status' => StatusPesanKeluar::class,
            'Tujuan' => 'encrypted',
            'IdReferensi' => 'integer',
            'Percobaan' => 'integer',
            'TerkirimPada' => 'datetime',
        ];
    }

    public function UbahStatus(StatusPesanKeluar $tujuan): void
    {
        if (! $this->Status->BisaBerubahKe($tujuan)) {
            throw new LogicException("Status pesan keluar {$this->Status->value} tidak bisa berubah ke {$tujuan->value}.");
        }

        $this->Status = $tujuan;
    }
}
