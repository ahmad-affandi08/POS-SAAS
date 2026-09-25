<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pembelian\Enum\StatusFakturPembelian;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Faktur pembelian `FB/{YYMM}/{SEQ4}` (F-04 fase 1) atas GRN satu pemasok & outlet; jurnal J-04.2 saat simpan.
 * Sisa hutang = Total − JumlahDibayar − JumlahRetur (tidak pernah negatif). Setelah disimpan hanya status, saldo
 * terpakai (dibayar/retur), dan pembatalan yang berubah. `PathLampiran` tidak pernah dikirim ke browser.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property string $Nomor
 * @property string $NomorFakturPemasok
 * @property int|null $IdPemasok
 * @property int|null $IdOutlet
 * @property Carbon $Tanggal
 * @property Carbon $JatuhTempo
 * @property int $TerminHari
 * @property StatusFakturPembelian $Status
 * @property string|null $TarifPpn
 * @property int|null $PengaliDppPembilang
 * @property int|null $PengaliDppPenyebut
 * @property bool $PpnDikreditkan
 * @property string $NilaiPenerimaan
 * @property string $Subtotal
 * @property string $Ongkir
 * @property string $Pajak
 * @property string $SelisihHarga
 * @property string $Total
 * @property string $JumlahDibayar
 * @property string $JumlahRetur
 * @property bool $BelanjaStok
 * @property string|null $Catatan
 * @property string|null $PathLampiran
 * @property string|null $NamaLampiran
 * @property string|null $MimeLampiran
 * @property int|null $UkuranLampiran
 * @property int|null $IdJurnal
 * @property int|null $IdJurnalPembatalan
 * @property int|null $DibuatOleh
 * @property int|null $DibatalkanOleh
 * @property Carbon|null $DibatalkanPada
 * @property string|null $AlasanBatal
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read Collection<int, FakturPembelianDetail> $Detail
 */
final class FakturPembelian extends ModelDasar
{
    use JagaDokumenPembelian;
    use MilikTenant;

    public const JENIS_DOKUMEN = 'FakturPembelian';

    protected $table = 'FakturPembelian';

    /** @var list<string> */
    protected $hidden = ['PathLampiran'];

    /** @var list<string> */
    protected $guarded = ['Id', 'KunciNomorPemasok'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'Status' => 'BelumDibayar',
        'TarifPpn' => null,
        'PengaliDppPembilang' => null,
        'PengaliDppPenyebut' => null,
        'PpnDikreditkan' => false,
        'JumlahDibayar' => '0.00',
        'JumlahRetur' => '0.00',
        'BelanjaStok' => false,
        'Catatan' => null,
        'PathLampiran' => null,
        'NamaLampiran' => null,
        'MimeLampiran' => null,
        'UkuranLampiran' => null,
        'IdJurnal' => null,
        'IdJurnalPembatalan' => null,
    ];

    /**
     * @return list<string>
     */
    public function AmbilKolomBolehBerubah(): array
    {
        return ['Status', 'IdJurnal', 'JumlahDibayar', 'JumlahRetur', 'IdJurnalPembatalan', 'DibatalkanOleh', 'DibatalkanPada', 'AlasanBatal'];
    }

    public function AmbilSisa(): Uang
    {
        return Uang::Dari($this->Total)->Kurangi(Uang::Dari($this->JumlahDibayar))->Kurangi(Uang::Dari($this->JumlahRetur));
    }

    /** Status terbuka dari sisa hutang: nol = Lunas; belum ada pembayaran = BelumDibayar; selain itu DibayarSebagian. */
    public function HitungStatusTerbuka(): StatusFakturPembelian
    {
        if (! $this->AmbilSisa()->BernilaiNegatif() && $this->AmbilSisa()->BernilaiNol()) {
            return StatusFakturPembelian::Lunas;
        }

        return Uang::Dari($this->JumlahDibayar)->BernilaiNol() ? StatusFakturPembelian::BelumDibayar : StatusFakturPembelian::DibayarSebagian;
    }

    /** Status menyesuaikan sisa hutang setelah pembayaran/retur (tanpa perubahan bila sama). */
    public function SelaraskanStatus(): void
    {
        $tujuan = $this->HitungStatusTerbuka();

        if ($tujuan !== $this->Status) {
            $this->UbahStatus($tujuan);
        }
    }

    /**
     * @throws LogicException bila perpindahan status tidak diizinkan
     */
    public function UbahStatus(StatusFakturPembelian $tujuan): void
    {
        if (! $this->Status->BisaBerubahKe($tujuan)) {
            throw new LogicException("Status faktur pembelian {$this->Status->value} tidak bisa berubah ke {$tujuan->value}.");
        }

        $this->Status = $tujuan;
    }

    /**
     * @return HasMany<FakturPembelianDetail, $this>
     */
    public function Detail(): HasMany
    {
        return $this->hasMany(FakturPembelianDetail::class, 'IdFakturPembelian', 'Id')->orderBy('Urutan');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Tanggal' => 'date',
            'JatuhTempo' => 'date',
            'Status' => StatusFakturPembelian::class,
            'TerminHari' => 'integer',
            'PpnDikreditkan' => 'boolean',
            'BelanjaStok' => 'boolean',
            'PengaliDppPembilang' => 'integer',
            'PengaliDppPenyebut' => 'integer',
            'UkuranLampiran' => 'integer',
            'DibatalkanPada' => 'datetime',
        ];
    }
}
