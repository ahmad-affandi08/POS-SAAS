<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Pelanggan\Enum\JenisPengingatPiutang;
use App\Domain\Pelanggan\Enum\KanalPengingatPiutang;
use App\Domain\Pelanggan\Enum\StatusPengingatPiutang;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Satu pengingat piutang ke pelanggan (D-23 D bagian 4b), dikirim tugas antrean `KirimPengingatPiutangTugas`.
 * `Tujuan` (nomor/email pelanggan) terenkripsi, tidak pernah diserialisasi atau dicatat di log. Status hanya lewat
 * `UbahStatus()`.
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdPiutang
 * @property JenisPengingatPiutang $Jenis
 * @property KanalPengingatPiutang $Kanal
 * @property string $Tujuan
 * @property StatusPengingatPiutang $Status
 * @property string|null $Penyedia
 * @property string|null $IdPesanPenyedia
 * @property string|null $PesanGalat
 * @property int $Percobaan
 * @property Carbon|null $TerkirimPada
 * @property string|null $KunciOtomatis
 * @property int|null $DikirimOleh
 * @property Carbon|null $DibuatPada
 */
final class PengingatPiutang extends ModelDasar
{
    use MilikTenant;

    public const PANJANG_PESAN_GALAT = 300;

    protected $table = 'PengingatPiutang';

    /** @var list<string> */
    protected $hidden = ['Tujuan'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'Penyedia' => null,
        'IdPesanPenyedia' => null,
        'PesanGalat' => null,
        'Percobaan' => 0,
        'TerkirimPada' => null,
        'KunciOtomatis' => null,
        'DikirimOleh' => null,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'Jenis' => JenisPengingatPiutang::class,
            'Kanal' => KanalPengingatPiutang::class,
            'Status' => StatusPengingatPiutang::class,
            'Tujuan' => 'encrypted',
            'Percobaan' => 'integer',
            'TerkirimPada' => 'datetime',
        ];
    }

    public function UbahStatus(StatusPengingatPiutang $tujuan): void
    {
        if (! $this->Status->BisaBerubahKe($tujuan)) {
            throw new LogicException("Status pengingat piutang {$this->Status->value} tidak bisa berubah ke {$tujuan->value}.");
        }

        $this->Status = $tujuan;
    }
}
