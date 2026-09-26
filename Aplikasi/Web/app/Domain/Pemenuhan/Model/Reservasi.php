<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pemenuhan\Enum\StatusReservasi;
use App\Domain\Pemenuhan\Enum\SumberReservasi;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Reservasi layanan jasa (F-07 mode service): satu layanan, satu staf (boleh kosong = siapa saja yang tersedia saat
 * dibuat), waktu mulai/selesai (UTC). `KodeAkses` rahasia untuk halaman publik lihat/batal. Status hanya lewat
 * `UbahStatus()` (`StatusReservasi::BisaBerubahKe`) dan dicatat di `RiwayatStatusDokumen` oleh Aksi.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property string $Nomor
 * @property int|null $IdPelanggan
 * @property string $NamaPelanggan
 * @property string $NoHp
 * @property int $IdProduk
 * @property int|null $IdKaryawan
 * @property Carbon $MulaiPada
 * @property Carbon $SelesaiPada
 * @property StatusReservasi $Status
 * @property SumberReservasi $Sumber
 * @property string|null $Catatan
 * @property string|null $AlasanBatal
 * @property string $KodeAkses
 * @property int|null $IdPenjualan
 * @property Carbon|null $HadirPada
 * @property Carbon|null $PengingatTerkirimPada
 * @property int|null $DibuatOleh
 * @property Carbon|null $DibuatPada
 */
final class Reservasi extends ModelDasar
{
    use MilikTenant;

    public const JENIS_DOKUMEN = 'Reservasi';

    protected $table = 'Reservasi';

    /** @var list<string> */
    protected $hidden = ['KodeAkses'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'IdPelanggan' => null,
        'IdKaryawan' => null,
        'Catatan' => null,
        'AlasanBatal' => null,
        'IdPenjualan' => null,
        'HadirPada' => null,
        'PengingatTerkirimPada' => null,
        'DibuatOleh' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'MulaiPada' => 'datetime',
            'SelesaiPada' => 'datetime',
            'Status' => StatusReservasi::class,
            'Sumber' => SumberReservasi::class,
            'HadirPada' => 'datetime',
            'PengingatTerkirimPada' => 'datetime',
        ];
    }

    public function UbahStatus(StatusReservasi $tujuan): void
    {
        if (! $this->Status->BisaBerubahKe($tujuan)) {
            throw new LogicException("Status reservasi {$this->Status->value} tidak bisa berubah ke {$tujuan->value}.");
        }

        $this->Status = $tujuan;
    }
}
