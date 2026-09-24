<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Persediaan\Enum\StatusImporStokAwal;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Satu berkas impor stok awal (DesainF05a B.2, C.7): berkas privat, pemetaan kolom, lokasi & tanggal bawaan,
 * status, dan penghitung. Impor hanya membuat dokumen stok awal Draf, tidak pernah memposting. Status hanya
 * berubah lewat `UbahStatus()` (`StatusImporStokAwal::BisaBerubahKe`).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdPengguna
 * @property int|null $IdGudangBawaan
 * @property Carbon|null $Tanggal
 * @property string $NamaBerkas
 * @property string $PathBerkas
 * @property string $HashBerkas
 * @property int $UkuranBerkas
 * @property string $Format
 * @property StatusImporStokAwal $Status
 * @property list<array{Indeks: int, Judul: string, Contoh: list<string>}>|null $KolomSumber
 * @property array<string, int|null>|null $Pemetaan
 * @property array<string, mixed>|null $Opsi
 * @property int $JumlahBaris
 * @property int $JumlahValid
 * @property int $JumlahGalat
 * @property int $JumlahDokumen
 * @property string|null $PesanGalat
 * @property Carbon|null $DivalidasiPada
 * @property Carbon|null $DiterapkanPada
 * @property Carbon|null $SelesaiPada
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class ImporStokAwal extends ModelDasar
{
    use MilikTenant;

    protected $table = 'ImporStokAwal';

    /** @var array<string, mixed> */
    protected $attributes = [
        'IdGudangBawaan' => null,
        'Tanggal' => null,
        'KolomSumber' => null,
        'Pemetaan' => null,
        'Opsi' => null,
        'JumlahBaris' => 0,
        'JumlahValid' => 0,
        'JumlahGalat' => 0,
        'JumlahDokumen' => 0,
        'PesanGalat' => null,
    ];

    /**
     * @return HasMany<ImporStokAwalBaris, $this>
     */
    public function Baris(): HasMany
    {
        return $this->hasMany(ImporStokAwalBaris::class, 'IdImporStokAwal', 'Id');
    }

    /**
     * @throws LogicException bila perpindahan status tidak diizinkan
     */
    public function UbahStatus(StatusImporStokAwal $tujuan): void
    {
        if (! $this->Status->BisaBerubahKe($tujuan)) {
            throw new LogicException("Status impor stok awal {$this->Status->value} tidak bisa berubah ke {$tujuan->value}.");
        }

        $this->Status = $tujuan;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Tanggal' => 'date',
            'UkuranBerkas' => 'integer',
            'Status' => StatusImporStokAwal::class,
            'KolomSumber' => 'array',
            'Pemetaan' => 'array',
            'Opsi' => 'array',
            'JumlahBaris' => 'integer',
            'JumlahValid' => 'integer',
            'JumlahGalat' => 'integer',
            'JumlahDokumen' => 'integer',
            'DivalidasiPada' => 'datetime',
            'DiterapkanPada' => 'datetime',
            'SelesaiPada' => 'datetime',
        ];
    }
}
