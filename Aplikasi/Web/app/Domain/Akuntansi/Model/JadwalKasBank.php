<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Model;

use App\Domain\Akuntansi\Enum\FrekuensiJadwalKasBank;
use App\Domain\Akuntansi\Enum\JenisTransaksiKasBank;
use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Transaksi kas & bank berulang (D-23 D bagian 2): pola transaksi yang dicatat otomatis tiap jatuh tempo lewat
 * `SimpanTransaksiKasBank`. Jadwal bukan dokumen keuangan: boleh diubah jumlahnya atau dihentikan; transaksi yang
 * sudah tercatat tetap append-only.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property JenisTransaksiKasBank $Jenis
 * @property int|null $IdOutlet
 * @property int $IdAkunSumber
 * @property int $IdAkunTujuan
 * @property string $Jumlah
 * @property string $Keterangan
 * @property FrekuensiJadwalKasBank $Frekuensi
 * @property Carbon $TanggalAcuan
 * @property Carbon $TanggalBerikutnya
 * @property bool $Aktif
 * @property int $JumlahDicatat
 * @property string|null $GalatTerakhir
 * @property int|null $DibuatOleh
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 * @property-read Akun $AkunSumber
 * @property-read Akun $AkunTujuan
 */
final class JadwalKasBank extends ModelDasar
{
    use MilikTenant;

    protected $table = 'JadwalKasBank';

    /** @var array<string, mixed> */
    protected $attributes = ['IdOutlet' => null, 'Aktif' => true, 'JumlahDicatat' => 0, 'GalatTerakhir' => null];

    /** @return BelongsTo<Akun, $this> */
    public function AkunSumber(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'IdAkunSumber', 'Id');
    }

    /** @return BelongsTo<Akun, $this> */
    public function AkunTujuan(): BelongsTo
    {
        return $this->belongsTo(Akun::class, 'IdAkunTujuan', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Jenis' => JenisTransaksiKasBank::class,
            'Frekuensi' => FrekuensiJadwalKasBank::class,
            'TanggalAcuan' => 'date',
            'TanggalBerikutnya' => 'date',
            'Aktif' => 'boolean',
            'JumlahDicatat' => 'integer',
            'Jumlah' => 'string',
        ];
    }
}
