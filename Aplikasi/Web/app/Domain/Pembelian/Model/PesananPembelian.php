<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pembelian\Enum\StatusPesananPembelian;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Pesanan pembelian (PO) `PO/{OUTLET}/{YYMM}/{SEQ4}` (F-04 fase 1). Bebas diubah selama Draf; setelah itu hanya kolom
 * perpindahan status (pengajuan, persetujuan, penolakan, pembatalan, penutupan) yang boleh berubah. Status hanya lewat
 * `UbahStatus()` dan dicatat di `RiwayatStatusDokumen` oleh Aksi.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Nomor
 * @property int $IdPemasok
 * @property int $IdGudang
 * @property int|null $IdOutlet
 * @property Carbon $Tanggal
 * @property Carbon|null $PerkiraanTiba
 * @property StatusPesananPembelian $Status
 * @property bool $DibuatOtomatis D-23 D: draf disiapkan sistem dari stok di bawah minimum.
 * @property int $TerminHari
 * @property bool $Pkp
 * @property string|null $TarifPpn
 * @property int|null $PengaliDppPembilang
 * @property int|null $PengaliDppPenyebut
 * @property bool $PpnDikreditkan
 * @property string $Subtotal
 * @property string $Diskon
 * @property string $Pajak
 * @property string $Ongkir
 * @property string $Total
 * @property string|null $Catatan
 * @property string|null $AlasanDitolak
 * @property int|null $DibuatOleh
 * @property int|null $DiubahOleh
 * @property int|null $DiajukanOleh
 * @property Carbon|null $DiajukanPada
 * @property int|null $DisetujuiOleh
 * @property Carbon|null $DisetujuiPada
 * @property int|null $DibatalkanOleh
 * @property Carbon|null $DibatalkanPada
 * @property string|null $AlasanBatal
 * @property int|null $DitutupOleh
 * @property Carbon|null $DitutupPada
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read Pemasok $Pemasok
 * @property-read Collection<int, PesananPembelianDetail> $Detail
 */
final class PesananPembelian extends ModelDasar
{
    use JagaDokumenPembelian;
    use MilikTenant;

    public const JENIS_DOKUMEN = 'PesananPembelian';

    private const KOLOM_STATUS = [
        'Status', 'AlasanDitolak', 'DiajukanOleh', 'DiajukanPada', 'DisetujuiOleh', 'DisetujuiPada', 'DibatalkanOleh',
        'DibatalkanPada', 'AlasanBatal', 'DitutupOleh', 'DitutupPada', 'DiubahOleh',
    ];

    protected $table = 'PesananPembelian';

    /** @var array<string, mixed> */
    protected $attributes = [
        'Status' => 'Draf',
        'DibuatOtomatis' => false,
        'PerkiraanTiba' => null,
        'TarifPpn' => null,
        'PengaliDppPembilang' => null,
        'PengaliDppPenyebut' => null,
        'PpnDikreditkan' => false,
        'Catatan' => null,
        'AlasanDitolak' => null,
    ];

    /**
     * @return list<string>|null
     */
    public function AmbilKolomBolehBerubah(): ?array
    {
        $asal = $this->getOriginal('Status');
        $asal = $asal instanceof StatusPesananPembelian ? $asal : StatusPesananPembelian::tryFrom((string) $asal);

        return $asal === StatusPesananPembelian::Draf ? null : self::KOLOM_STATUS;
    }

    /**
     * @throws LogicException bila perpindahan status tidak diizinkan
     */
    public function UbahStatus(StatusPesananPembelian $tujuan): void
    {
        if (! $this->Status->BisaBerubahKe($tujuan)) {
            throw new LogicException("Status pesanan pembelian {$this->Status->value} tidak bisa berubah ke {$tujuan->value}.");
        }

        $this->Status = $tujuan;
    }

    /**
     * @return BelongsTo<Pemasok, $this>
     */
    public function Pemasok(): BelongsTo
    {
        return $this->belongsTo(Pemasok::class, 'IdPemasok', 'Id')->withTrashed();
    }

    /**
     * @return HasMany<PesananPembelianDetail, $this>
     */
    public function Detail(): HasMany
    {
        return $this->hasMany(PesananPembelianDetail::class, 'IdPesananPembelian', 'Id')->orderBy('Urutan');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'DibuatOtomatis' => 'boolean',
            'Tanggal' => 'date',
            'PerkiraanTiba' => 'date',
            'Status' => StatusPesananPembelian::class,
            'TerminHari' => 'integer',
            'Pkp' => 'boolean',
            'PpnDikreditkan' => 'boolean',
            'PengaliDppPembilang' => 'integer',
            'PengaliDppPenyebut' => 'integer',
            'DiajukanPada' => 'datetime',
            'DisetujuiPada' => 'datetime',
            'DibatalkanPada' => 'datetime',
            'DitutupPada' => 'datetime',
        ];
    }
}
