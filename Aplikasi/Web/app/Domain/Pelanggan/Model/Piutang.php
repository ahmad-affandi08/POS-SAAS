<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pelanggan\Enum\StatusPiutang;
use Illuminate\Support\Carbon;

/**
 * Piutang dari penjualan tempo (F-12): satu per penjualan. Sisa = Jumlah − JumlahDibayar − JumlahDikurangi.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int|null $IdPelanggan
 * @property int $IdPenjualan
 * @property int $IdOutlet
 * @property string $Nomor
 * @property Carbon $TanggalBisnis
 * @property Carbon $JatuhTempo
 * @property string $Jumlah
 * @property string $JumlahDibayar
 * @property string $JumlahDikurangi
 * @property StatusPiutang $Status
 */
final class Piutang extends ModelDasar
{
    use MilikTenant;

    public const JENIS_DOKUMEN = 'Piutang';

    protected $table = 'Piutang';

    /** @var array<string, mixed> */
    protected $attributes = ['JumlahDibayar' => '0.00', 'JumlahDikurangi' => '0.00', 'Status' => 'BelumLunas'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'TanggalBisnis' => 'date',
            'JatuhTempo' => 'date',
            'Jumlah' => 'decimal:2',
            'JumlahDibayar' => 'decimal:2',
            'JumlahDikurangi' => 'decimal:2',
            'Status' => StatusPiutang::class,
        ];
    }

    public function AmbilSisa(): Uang
    {
        return Uang::Dari($this->Jumlah)->Kurangi(Uang::Dari($this->JumlahDibayar))->Kurangi(Uang::Dari($this->JumlahDikurangi));
    }

    /** Status mengikuti sisa (piutang dibatalkan tidak berubah). */
    public function SelaraskanStatus(): void
    {
        if ($this->Status === StatusPiutang::Dibatalkan) {
            return;
        }

        $this->Status = match (true) {
            $this->AmbilSisa()->Bandingkan(Uang::Nol()) <= 0 => StatusPiutang::Lunas,
            Uang::Dari($this->JumlahDibayar)->BernilaiNol() => StatusPiutang::BelumLunas,
            default => StatusPiutang::DibayarSebagian,
        };
    }
}
