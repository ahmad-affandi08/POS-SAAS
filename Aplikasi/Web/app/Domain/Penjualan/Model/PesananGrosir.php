<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Penjualan\Enum\StatusPesananGrosir;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Sales order grosir `PG/{OUTLET}/{YYMM}/{SEQ4}` (F-12, §9.7, D-32). Bebas diubah selama Draf; setelah dikonfirmasi
 * hanya kolom perpindahan status dan `JumlahTerkirim` di barisnya yang bergerak.
 *
 * **Bukan peristiwa akuntansi:** tidak ada jurnal maupun mutasi stok di sini. Pengakuan HPP, pendapatan, dan PPN
 * terjadi saat penyerahan barang lewat `SuratJalan` (BR-12.2, J-12.1).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Nomor
 * @property int $IdPelanggan
 * @property int $IdOutlet
 * @property Carbon $Tanggal
 * @property Carbon|null $TanggalKirimDiminta
 * @property StatusPesananGrosir $Status
 * @property int $TerminHari
 * @property string|null $TarifPpn
 * @property int|null $PengaliDppPembilang
 * @property int|null $PengaliDppPenyebut
 * @property string $Subtotal
 * @property string $Diskon
 * @property string $DasarPengenaanPajak
 * @property string $Pajak
 * @property string $Total
 * @property string|null $Catatan
 * @property int|null $IdPenyetujuKredit
 * @property string|null $AlasanPersetujuanKredit
 * @property int|null $DibuatOleh
 * @property int|null $DiubahOleh
 * @property int|null $DikonfirmasiOleh
 * @property Carbon|null $DikonfirmasiPada
 * @property Carbon|null $SelesaiPada
 * @property int|null $DibatalkanOleh
 * @property Carbon|null $DibatalkanPada
 * @property string|null $AlasanBatal
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read Pelanggan $Pelanggan
 * @property-read Collection<int, PesananGrosirDetail> $Detail
 */
final class PesananGrosir extends ModelDasar
{
    use JagaDokumenGrosir;
    use MilikTenant;

    public const JENIS_DOKUMEN = 'PesananGrosir';

    private const KOLOM_STATUS = [
        'Status', 'IdPenyetujuKredit', 'AlasanPersetujuanKredit', 'TerminHari', 'TarifPpn', 'PengaliDppPembilang',
        'PengaliDppPenyebut', 'DikonfirmasiOleh', 'DikonfirmasiPada', 'SelesaiPada', 'DibatalkanOleh',
        'DibatalkanPada', 'AlasanBatal', 'DiubahOleh',
    ];

    protected $table = 'PesananGrosir';

    /** @var array<string, mixed> */
    protected $attributes = [
        'Status' => 'Draf',
        'TanggalKirimDiminta' => null,
        'TerminHari' => 0,
        'TarifPpn' => null,
        'PengaliDppPembilang' => null,
        'PengaliDppPenyebut' => null,
        'Catatan' => null,
        'IdPenyetujuKredit' => null,
        'AlasanPersetujuanKredit' => null,
    ];

    /**
     * Snapshot pajak & termin ikut boleh berubah saat konfirmasi, karena justru di sanalah nilainya ditetapkan
     * (BR-12.6); setelah itu statusnya tidak lagi Draf sehingga daftar ini yang berlaku.
     *
     * @return list<string>|null
     */
    public function AmbilKolomBolehBerubah(): ?array
    {
        $asal = $this->getOriginal('Status');
        $asal = $asal instanceof StatusPesananGrosir ? $asal : StatusPesananGrosir::tryFrom((string) $asal);

        return $asal === StatusPesananGrosir::Draf ? null : self::KOLOM_STATUS;
    }

    /**
     * @throws LogicException bila perpindahan status tidak diizinkan
     */
    public function UbahStatus(StatusPesananGrosir $tujuan): void
    {
        if (! $this->Status->BisaBerubahKe($tujuan)) {
            throw new LogicException("Status pesanan grosir {$this->Status->value} tidak bisa berubah ke {$tujuan->value}.");
        }

        $this->Status = $tujuan;
    }

    public function AmbilTotal(): Uang
    {
        return Uang::Dari($this->Total);
    }

    /**
     * @return BelongsTo<Pelanggan, $this>
     */
    public function Pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class, 'IdPelanggan', 'Id')->withTrashed();
    }

    /**
     * @return HasMany<PesananGrosirDetail, $this>
     */
    public function Detail(): HasMany
    {
        return $this->hasMany(PesananGrosirDetail::class, 'IdPesananGrosir', 'Id')->orderBy('Urutan');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Tanggal' => 'date',
            'TanggalKirimDiminta' => 'date',
            'Status' => StatusPesananGrosir::class,
            'TerminHari' => 'integer',
            'TarifPpn' => 'decimal:6',
            'PengaliDppPembilang' => 'integer',
            'PengaliDppPenyebut' => 'integer',
            'Subtotal' => 'decimal:2',
            'Diskon' => 'decimal:2',
            'DasarPengenaanPajak' => 'decimal:2',
            'Pajak' => 'decimal:2',
            'Total' => 'decimal:2',
            'DikonfirmasiPada' => 'datetime',
            'SelesaiPada' => 'datetime',
            'DibatalkanPada' => 'datetime',
        ];
    }
}
