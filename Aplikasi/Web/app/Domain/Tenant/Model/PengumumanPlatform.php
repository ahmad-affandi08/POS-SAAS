<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Tenant\Enum\JenisPengumuman;
use App\Domain\Tenant\Enum\StatusPengumuman;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Pengumuman platform P-10 (PGL-19). Data platform (tanpa `MilikTenant`); dikelola Platform Pengelola, dibaca
 * `PengumumanBerlaku` untuk back-office & `konfigurasi-aplikasi`. `Sasaran` kosong per kunci = semua.
 *
 * @property int $Id
 * @property string $Uuid
 * @property string $Judul
 * @property string $Isi
 * @property JenisPengumuman $Jenis
 * @property array{KodePaket?: list<string>, Sektor?: list<string>, Platform?: list<string>, VersiMinimal?: string|null, VersiMaksimal?: string|null} $Sasaran
 * @property string|null $Tautan
 * @property Carbon $TampilMulai
 * @property Carbon $TampilSampai
 * @property Carbon|null $PemeliharaanMulai
 * @property Carbon|null $PemeliharaanSelesai
 * @property StatusPengumuman $Status
 * @property Carbon|null $DiterbitkanPada
 * @property Carbon|null $DicabutPada
 * @property string|null $AlasanCabut
 * @property int|null $DibuatOleh
 * @property int|null $DiterbitkanOleh
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class PengumumanPlatform extends ModelDasar
{
    protected $table = 'PengumumanPlatform';

    /** @var array<string, mixed> */
    protected $attributes = [
        'Status' => 'Draf',
        'Tautan' => null,
        'PemeliharaanMulai' => null,
        'PemeliharaanSelesai' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Jenis' => JenisPengumuman::class,
            'Status' => StatusPengumuman::class,
            'Sasaran' => 'array',
            'TampilMulai' => 'datetime',
            'TampilSampai' => 'datetime',
            'PemeliharaanMulai' => 'datetime',
            'PemeliharaanSelesai' => 'datetime',
            'DiterbitkanPada' => 'datetime',
            'DicabutPada' => 'datetime',
        ];
    }

    public function UbahStatus(StatusPengumuman $tujuan): void
    {
        if (! $this->Status->BisaBerubahKe($tujuan)) {
            throw new LogicException("Status pengumuman {$this->Status->value} tidak bisa berubah ke {$tujuan->value}.");
        }

        $this->Status = $tujuan;
    }
}
