<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Baris penjualan dengan snapshot harga, pilihan, pajak, diskon, dan HPP (PRD §15.3, BR-07.2). Append-only;
 * `HppSatuan` & `TotalHpp` diisi sekali (dari 0) di transaksi penerimaan setelah mutasi stok tercatat.
 *
 * `Pilihan`: `[{UuidPilihan, Nama, Harga}]`. `SnapshotPajak`: `[{Kode, Tarif, PengaliDppPembilang, PengaliDppPenyebut,
 * DasarPengenaan}]` pajak yang berlaku untuk baris ini. `DiskonManual`: `{Persen}` atau `{Jumlah}`.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdPenjualan
 * @property int $Urutan
 * @property int $IdProduk
 * @property string $NamaProduk
 * @property int $IdSatuan
 * @property string $KonversiKeDasar
 * @property string $Jumlah
 * @property string $JumlahDasar
 * @property string $HargaSatuan
 * @property string $HargaPilihan
 * @property list<array{UuidPilihan: string, Nama: string, Harga: string}>|null $Pilihan
 * @property bool $HargaTermasukPajak
 * @property list<array{Kode: string, Tarif: string, PengaliDppPembilang: int, PengaliDppPenyebut: int, DasarPengenaan: string}>|null $SnapshotPajak
 * @property array{Persen?: string, Jumlah?: string}|null $DiskonManual
 * @property string $Bruto
 * @property string $JumlahDiskon
 * @property string $JumlahDiskonPesanan
 * @property string $BiayaLayanan
 * @property string $JumlahPajak
 * @property string $PajakEksklusif
 * @property string $TotalBaris
 * @property string $HppSatuan
 * @property string $TotalHpp
 * @property string|null $Catatan
 * @property Carbon|null $DibuatPada
 */
final class PenjualanDetail extends ModelDasar
{
    use MilikTenant;

    protected $table = 'PenjualanDetail';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'KonversiKeDasar' => 'decimal:4',
            'Jumlah' => 'decimal:4',
            'JumlahDasar' => 'decimal:4',
            'HargaSatuan' => 'decimal:2',
            'HargaPilihan' => 'decimal:2',
            'Pilihan' => 'array',
            'HargaTermasukPajak' => 'boolean',
            'SnapshotPajak' => 'array',
            'DiskonManual' => 'array',
            'Bruto' => 'decimal:2',
            'JumlahDiskon' => 'decimal:2',
            'JumlahDiskonPesanan' => 'decimal:2',
            'BiayaLayanan' => 'decimal:2',
            'JumlahPajak' => 'decimal:2',
            'PajakEksklusif' => 'decimal:2',
            'TotalBaris' => 'decimal:2',
            'HppSatuan' => 'decimal:6',
            'TotalHpp' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        self::updating(function (PenjualanDetail $detail): void {
            $hppBelumDiisi = self::CekNol($detail->getOriginal('TotalHpp')) && self::CekNol($detail->getOriginal('HppSatuan'));

            foreach (array_keys($detail->getDirty()) as $kolom) {
                if ($kolom !== self::UPDATED_AT && ! (in_array($kolom, ['HppSatuan', 'TotalHpp'], true) && $hppBelumDiisi)) {
                    throw new LogicException("Baris penjualan append-only: kolom {$kolom} tidak boleh diubah.");
                }
            }
        });

        self::deleting(function (): void {
            throw new LogicException('Baris penjualan tidak boleh dihapus.');
        });
    }

    /**
     * @return BelongsTo<Penjualan, $this>
     */
    public function Penjualan(): BelongsTo
    {
        return $this->belongsTo(Penjualan::class, 'IdPenjualan', 'Id');
    }

    private static function CekNol(mixed $nilai): bool
    {
        return $nilai === null || preg_match('/^0+(\.0+)?$/', (string) $nilai) === 1;
    }
}
