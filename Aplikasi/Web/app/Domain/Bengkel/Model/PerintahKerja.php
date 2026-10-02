<?php

declare(strict_types=1);

namespace App\Domain\Bengkel\Model;

use App\Domain\Bengkel\Enum\LewatPersetujuan;
use App\Domain\Bengkel\Enum\StatusPerintahKerja;
use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Perintah kerja (work order) bengkel (§9.10, §15 `PerintahKerja`). Bukan peristiwa akuntansi: stok sparepart,
 * pendapatan, dan pajak baru bergerak saat ditagih lewat penjualan kasir (`IdPenjualan`). Status hanya lewat
 * `UbahStatus()` (`StatusPerintahKerja::BisaBerubahKe`) dan dicatat di `RiwayatStatusDokumen` oleh Aksi.
 *
 * Token persetujuan pelanggan: `HashTokenPersetujuan` (sha256) yang dicari, `TokenPersetujuan` terenkripsi hanya
 * untuk menampilkan ulang tautannya di back-office. Keduanya disembunyikan dari serialisasi.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property string $Nomor
 * @property int $IdPelanggan
 * @property int $IdKendaraan
 * @property StatusPerintahKerja $Status
 * @property int|null $KmMasuk
 * @property string $Keluhan
 * @property string|null $Diagnosis
 * @property Carbon|null $EstimasiSelesaiPada
 * @property string|null $CatatanQc
 * @property string $Subtotal
 * @property string $Diskon
 * @property string $Pajak
 * @property string $Total
 * @property string $TotalDisetujui
 * @property string|null $TokenPersetujuan
 * @property string|null $HashTokenPersetujuan
 * @property Carbon|null $TokenPersetujuanKedaluwarsaPada
 * @property Carbon|null $PersetujuanDikirimPada
 * @property Carbon|null $DiputuskanPada
 * @property LewatPersetujuan|null $DiputuskanLewat
 * @property int|null $DiputuskanOleh
 * @property string|null $HashIpPersetujuan
 * @property string|null $CatatanPelanggan
 * @property int|null $IdPenjualan
 * @property Carbon|null $DitagihPada
 * @property Carbon|null $ServisBerikutnyaPada
 * @property int|null $ServisBerikutnyaKm
 * @property Carbon|null $PengingatServisDiprosesPada
 * @property Carbon|null $PengingatServisTerkirimPada
 * @property string|null $AlasanBatal
 * @property int|null $DibuatOleh
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read Collection<int, PerintahKerjaDetail> $Detail
 * @property-read Kendaraan $Kendaraan
 */
final class PerintahKerja extends ModelDasar
{
    use MilikTenant;

    public const JENIS_DOKUMEN = 'PerintahKerja';

    protected $table = 'PerintahKerja';

    /** @var list<string> */
    protected $hidden = ['TokenPersetujuan', 'HashTokenPersetujuan', 'HashIpPersetujuan'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'KmMasuk' => null,
        'Diagnosis' => null,
        'EstimasiSelesaiPada' => null,
        'CatatanQc' => null,
        'Subtotal' => '0.00',
        'Diskon' => '0.00',
        'Pajak' => '0.00',
        'Total' => '0.00',
        'TotalDisetujui' => '0.00',
        'TokenPersetujuan' => null,
        'HashTokenPersetujuan' => null,
        'TokenPersetujuanKedaluwarsaPada' => null,
        'PersetujuanDikirimPada' => null,
        'DiputuskanPada' => null,
        'DiputuskanLewat' => null,
        'DiputuskanOleh' => null,
        'HashIpPersetujuan' => null,
        'CatatanPelanggan' => null,
        'IdPenjualan' => null,
        'DitagihPada' => null,
        'ServisBerikutnyaPada' => null,
        'ServisBerikutnyaKm' => null,
        'PengingatServisDiprosesPada' => null,
        'PengingatServisTerkirimPada' => null,
        'AlasanBatal' => null,
        'DibuatOleh' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Status' => StatusPerintahKerja::class,
            'DiputuskanLewat' => LewatPersetujuan::class,
            'KmMasuk' => 'integer',
            'ServisBerikutnyaKm' => 'integer',
            'EstimasiSelesaiPada' => 'datetime',
            'Subtotal' => 'decimal:2',
            'Diskon' => 'decimal:2',
            'Pajak' => 'decimal:2',
            'Total' => 'decimal:2',
            'TotalDisetujui' => 'decimal:2',
            'TokenPersetujuan' => 'encrypted',
            'TokenPersetujuanKedaluwarsaPada' => 'datetime',
            'PersetujuanDikirimPada' => 'datetime',
            'DiputuskanPada' => 'datetime',
            'DitagihPada' => 'datetime',
            'ServisBerikutnyaPada' => 'date',
            'PengingatServisDiprosesPada' => 'datetime',
            'PengingatServisTerkirimPada' => 'datetime',
        ];
    }

    /** @return HasMany<PerintahKerjaDetail, $this> */
    public function Detail(): HasMany
    {
        return $this->hasMany(PerintahKerjaDetail::class, 'IdPerintahKerja', 'Id')->orderBy('Urutan');
    }

    /** @return BelongsTo<Kendaraan, $this> */
    public function Kendaraan(): BelongsTo
    {
        return $this->belongsTo(Kendaraan::class, 'IdKendaraan', 'Id');
    }

    public function UbahStatus(StatusPerintahKerja $tujuan): void
    {
        if (! $this->Status->BisaBerubahKe($tujuan)) {
            throw new LogicException("Status perintah kerja {$this->Status->value} tidak bisa berubah ke {$tujuan->value}.");
        }

        $this->Status = $tujuan;
    }

    /** Tautan persetujuan masih bisa dipakai pelanggan untuk memutuskan. */
    public function CekTautanBerlaku(): bool
    {
        return $this->HashTokenPersetujuan !== null
            && $this->TokenPersetujuanKedaluwarsaPada !== null
            && $this->TokenPersetujuanKedaluwarsaPada->isFuture();
    }
}
