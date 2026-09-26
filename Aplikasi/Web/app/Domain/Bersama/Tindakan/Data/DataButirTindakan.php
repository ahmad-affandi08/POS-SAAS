<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tindakan\Data;

use App\Domain\Bersama\Tindakan\Enum\TingkatTindakan;

/**
 * Satu butir Kotak Tindakan (D-23 C). `jenisDokumen` terisi = rinciannya dokumen yang bisa ditandai "sudah dicek"
 * (`TinjauanDokumen`); kosong = butir pengingat yang selesai sendiri saat keadaannya berubah (misal stok sudah diisi).
 */
final readonly class DataButirTindakan
{
    /**
     * @param  list<DataRincianTindakan>  $rincian  paling banyak `BATAS_RINCIAN`
     */
    public function __construct(
        public string $kunci,
        public string $modul,
        public TingkatTindakan $tingkat,
        public string $judul,
        public string $keterangan,
        public int $jumlah,
        public string $tautan,
        public string $labelTautan,
        public array $rincian = [],
        public ?string $jenisDokumen = null,
    ) {}

    public const BATAS_RINCIAN = 20;

    /**
     * @return array<string, mixed>
     */
    public function KeLarik(bool $bolehTandai): array
    {
        return [
            'Kunci' => $this->kunci,
            'Modul' => $this->modul,
            'Tingkat' => $this->tingkat->value,
            'Judul' => $this->judul,
            'Keterangan' => $this->keterangan,
            'Jumlah' => $this->jumlah,
            'Tautan' => $this->tautan,
            'LabelTautan' => $this->labelTautan,
            'JenisDokumen' => $this->jenisDokumen,
            'BolehTandai' => $bolehTandai && $this->jenisDokumen !== null,
            'Rincian' => array_map(fn (DataRincianTindakan $r): array => $r->KeLarik(), $this->rincian),
        ];
    }
}
