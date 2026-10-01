<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Model;

use App\Domain\Akuntansi\Enum\ArahGiro;
use App\Domain\Akuntansi\Enum\JenisSumberGiro;
use App\Domain\Akuntansi\Enum\StatusGiro;
use App\Domain\Bersama\Dokumen\Model\JagaDokumenTerposting;
use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Bilyet giro/cek mundur (v3.42, F-12). Satu giro per pelunasan piutang/pembayaran hutang. Selama Menunggu nilainya di
 * akun Giro Mundur Diterima (masuk) atau Hutang Giro (keluar); Cair = jurnal ke rekening bank, Ditolak = dokumen asal
 * dibalik sehingga piutang/hutang kembali.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property ArahGiro $Arah
 * @property JenisSumberGiro $JenisSumber
 * @property int $IdSumber
 * @property string $NomorSumber
 * @property string $NamaPihak
 * @property string $NomorGiro
 * @property string $NamaBank
 * @property Carbon $TanggalTerima
 * @property Carbon $TanggalJatuhTempo
 * @property string $Jumlah
 * @property StatusGiro $Status
 * @property int|null $IdAkunCair
 * @property Carbon|null $TanggalCair
 * @property int|null $IdJurnalCair
 * @property string|null $AlasanTolak
 * @property int|null $DiputuskanOleh
 * @property Carbon|null $DiputuskanPada
 * @property int|null $DibuatOleh
 * @property Carbon|null $DibuatPada
 * @property Carbon|null $DiubahPada
 */
final class Giro extends ModelDasar
{
    use JagaDokumenTerposting;
    use MilikTenant;

    protected $table = 'Giro';

    /** @var array<string, mixed> */
    protected $attributes = ['Status' => 'Menunggu'];

    /**
     * @return list<string>
     */
    public function AmbilKolomBolehBerubah(): array
    {
        return ['Status', 'IdAkunCair', 'TanggalCair', 'IdJurnalCair', 'AlasanTolak', 'DiputuskanOleh', 'DiputuskanPada'];
    }

    /**
     * @throws LogicException bila perpindahan status tidak diizinkan
     */
    public function UbahStatus(StatusGiro $tujuan): void
    {
        if (! $this->Status->BisaBerubahKe($tujuan)) {
            throw new LogicException("Status giro {$this->Status->value} tidak bisa berubah ke {$tujuan->value}.");
        }

        $this->Status = $tujuan;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Arah' => ArahGiro::class,
            'JenisSumber' => JenisSumberGiro::class,
            'Status' => StatusGiro::class,
            'TanggalTerima' => 'date',
            'TanggalJatuhTempo' => 'date',
            'TanggalCair' => 'date',
            'DiputuskanPada' => 'datetime',
        ];
    }
}
