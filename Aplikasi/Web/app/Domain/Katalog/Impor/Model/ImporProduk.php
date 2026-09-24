<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Katalog\Impor\Enum\StatusImporProduk;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Satu berkas impor produk (F-03 BR-03.6): berkas privat `impor/{IdTenant}/…`, pemetaan kolom, opsi, status, dan
 * penghitung hasil. Status hanya berubah lewat `UbahStatus()` (`StatusImporProduk::BisaBerubahKe`).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdPengguna
 * @property string $Sumber
 * @property string $NamaBerkas
 * @property string $PathBerkas
 * @property string $HashBerkas
 * @property int $UkuranBerkas
 * @property string $Format
 * @property StatusImporProduk $Status
 * @property list<array{Indeks: int, Judul: string, Contoh: list<string>}>|null $KolomSumber
 * @property array<string, int|null>|null $Pemetaan
 * @property array<string, mixed>|null $Opsi
 * @property int $JumlahBaris
 * @property int $JumlahValid
 * @property int $JumlahGalat
 * @property int $JumlahDiterapkan
 * @property int $JumlahDibuat
 * @property int $JumlahDiperbarui
 * @property int $JumlahDilewati
 * @property int $JumlahGagal
 * @property string|null $PesanGalat
 * @property Carbon|null $DivalidasiPada
 * @property Carbon|null $DiterapkanMulaiPada
 * @property Carbon|null $SelesaiPada
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class ImporProduk extends ModelDasar
{
    use MilikTenant;

    protected $table = 'ImporProduk';

    /**
     * @return HasMany<ImporProdukBaris, $this>
     */
    public function Baris(): HasMany
    {
        return $this->hasMany(ImporProdukBaris::class, 'IdImporProduk', 'Id');
    }

    /**
     * @throws LogicException bila perpindahan status tidak diizinkan
     */
    public function UbahStatus(StatusImporProduk $tujuan): void
    {
        if (! $this->Status->BisaBerubahKe($tujuan)) {
            throw new LogicException("Status impor {$this->Status->value} tidak bisa berubah ke {$tujuan->value}.");
        }

        $this->Status = $tujuan;
    }

    /**
     * Opsi pembaca berkas yang ditetapkan saat unggah (baris judul, pemisah CSV).
     *
     * @return array{BarisJudul: int, PemisahCsv: string|null, Lembar: string|null}
     */
    public function AmbilOpsiPembaca(): array
    {
        $pembaca = (array) (($this->Opsi ?? [])['Pembaca'] ?? []);

        return [
            'BarisJudul' => (int) ($pembaca['BarisJudul'] ?? 1),
            'PemisahCsv' => isset($pembaca['PemisahCsv']) && is_string($pembaca['PemisahCsv']) ? $pembaca['PemisahCsv'] : null,
            'Lembar' => isset($pembaca['Lembar']) && is_string($pembaca['Lembar']) ? $pembaca['Lembar'] : null,
        ];
    }

    /**
     * @return list<string>
     */
    public function AmbilPeringatan(): array
    {
        return array_values(array_map('strval', (array) (($this->Opsi ?? [])['Peringatan'] ?? [])));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Status' => StatusImporProduk::class,
            'KolomSumber' => 'array',
            'Pemetaan' => 'array',
            'Opsi' => 'array',
            'DivalidasiPada' => 'datetime',
            'DiterapkanMulaiPada' => 'datetime',
            'SelesaiPada' => 'datetime',
        ];
    }
}
