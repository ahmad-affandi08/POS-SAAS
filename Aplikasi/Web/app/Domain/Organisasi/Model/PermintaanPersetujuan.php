<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Model;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Organisasi\Enum\StatusPermintaanPersetujuan;
use Illuminate\Support\Carbon;

/**
 * Permintaan persetujuan jarak jauh (X4, §19.2) dari perangkat kasir. Isi permintaan tidak pernah diubah; status hanya
 * lewat `UbahStatus()` dan dicatat di `RiwayatStatusDokumen` oleh Aksi.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property int $IdPerangkat
 * @property int $IdPemohon
 * @property string|null $Izin
 * @property bool $HanyaPemilik
 * @property string $Judul
 * @property list<array{Label: string, Nilai: string}> $Rincian
 * @property string|null $Nilai
 * @property StatusPermintaanPersetujuan $Status
 * @property Carbon $KedaluwarsaPada
 * @property int|null $DiputuskanOleh
 * @property Carbon|null $DiputuskanPada
 * @property string|null $AlasanTolak
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class PermintaanPersetujuan extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PermintaanPersetujuan';

    protected $attributes = ['Status' => 'Menunggu', 'HanyaPemilik' => false];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'HanyaPemilik' => 'boolean',
            'Rincian' => 'array',
            'Status' => StatusPermintaanPersetujuan::class,
            'KedaluwarsaPada' => 'datetime',
            'DiputuskanPada' => 'datetime',
        ];
    }

    /** Menunggu tetapi sudah lewat masa berlaku. */
    public function CekLewatWaktu(): bool
    {
        return $this->Status === StatusPermintaanPersetujuan::Menunggu && $this->KedaluwarsaPada->isPast();
    }

    public function UbahStatus(StatusPermintaanPersetujuan $tujuan): void
    {
        if (! $this->Status->BisaBerubahKe($tujuan)) {
            throw new PelanggaranAturanBisnis('StatusTidakSesuai', "Permintaan ini sudah {$this->Status->AmbilLabel()}.", 'Status', 409);
        }

        $this->Status = $tujuan;
    }
}
