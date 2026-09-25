<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Kasir\Enum\StatusShift;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Shift kasir per perangkat (PRD F-06, §15.3). `Uuid` dibuat di perangkat (ULID) dan menjadi kunci idempotensi
 * sinkron. Tidak pernah dihapus. Data pembukaan (kas awal, pecahan, pembuka, waktu) tidak berubah setelah diterima;
 * perubahan berikutnya hanya status & kolom tutup shift (F-11) serta tanda tinjauan.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdOutlet
 * @property int $IdPerangkat
 * @property StatusShift $Status
 * @property bool $Bersama
 * @property int $DibukaOleh
 * @property Carbon $DibukaPada
 * @property Carbon $TanggalBisnis
 * @property string $KasAwal
 * @property list<array{Nominal: string, Jumlah: int}>|null $PecahanKasAwal
 * @property bool $PerluTinjauan
 * @property string|null $AlasanTinjauan
 * @property Carbon $DiterimaPada
 * @property int|null $DitutupOleh
 * @property Carbon|null $DitutupPada
 * @property string|null $KasSeharusnya
 * @property string|null $KasAktual
 * @property string|null $Selisih
 * @property list<array{Nominal: string, Jumlah: int}>|null $PecahanKasAkhir
 * @property list<array{UuidMetodePembayaran: string, Jenis: string, Nama: string, JumlahSistem: string, JumlahDilaporkan: string|null}>|null $RingkasanNonTunai
 * @property string|null $AlasanSelisih
 * @property int|null $IdPenyetujuSelisih
 */
final class Shift extends ModelDasar
{
    use MilikTenant;

    /** Nilai `RiwayatStatusDokumen.JenisDokumen` untuk dokumen ini. */
    public const JENIS_DOKUMEN = 'Shift';

    /** Kolom pembukaan yang tidak boleh berubah setelah shift diterima. */
    private const KOLOM_PEMBUKAAN = ['IdTenant', 'IdOutlet', 'IdPerangkat', 'Uuid', 'Bersama', 'DibukaOleh', 'DibukaPada', 'TanggalBisnis', 'KasAwal', 'PecahanKasAwal', 'DiterimaPada'];

    /** Kolom tutup shift (F-11): hanya diisi saat shift berubah ke `Tertutup`, tidak diubah setelahnya. */
    private const KOLOM_TUTUP = ['DitutupOleh', 'DitutupPada', 'KasSeharusnya', 'KasAktual', 'Selisih', 'PecahanKasAkhir', 'RingkasanNonTunai', 'AlasanSelisih', 'IdPenyetujuSelisih'];

    protected $table = 'Shift';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Status' => StatusShift::class,
            'Bersama' => 'boolean',
            'DibukaPada' => 'datetime',
            'TanggalBisnis' => 'date',
            'KasAwal' => 'decimal:2',
            'PecahanKasAwal' => 'array',
            'PerluTinjauan' => 'boolean',
            'DiterimaPada' => 'datetime',
            'DitutupPada' => 'datetime',
            'KasSeharusnya' => 'decimal:2',
            'KasAktual' => 'decimal:2',
            'Selisih' => 'decimal:2',
            'PecahanKasAkhir' => 'array',
            'RingkasanNonTunai' => 'array',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (Shift $shift): void {
            foreach (self::KOLOM_PEMBUKAAN as $kolom) {
                if ($shift->isDirty($kolom)) {
                    throw new LogicException("Data pembukaan shift ({$kolom}) tidak boleh diubah.");
                }
            }

            $statusLama = $shift->getOriginal('Status');
            $statusLama = $statusLama instanceof StatusShift ? $statusLama : StatusShift::tryFrom((string) $statusLama);

            if ($shift->isDirty('Status') && ($statusLama === null || ! $statusLama->BisaBerubahKe($shift->Status))) {
                throw new LogicException("Status shift tidak boleh berubah dari {$statusLama?->value} ke {$shift->Status->value}.");
            }

            $sedangDitutup = $shift->isDirty('Status') && $shift->Status === StatusShift::Tertutup;

            foreach (self::KOLOM_TUTUP as $kolom) {
                if (! $sedangDitutup && $shift->isDirty($kolom)) {
                    throw new LogicException("Data tutup shift ({$kolom}) hanya diisi saat shift ditutup.");
                }
            }
        });

        self::deleting(function (): void {
            throw new LogicException('Shift tidak boleh dihapus.');
        });
    }

    /**
     * @return HasMany<MutasiKas, $this>
     */
    public function MutasiKas(): HasMany
    {
        return $this->hasMany(MutasiKas::class, 'IdShift', 'Id');
    }
}
