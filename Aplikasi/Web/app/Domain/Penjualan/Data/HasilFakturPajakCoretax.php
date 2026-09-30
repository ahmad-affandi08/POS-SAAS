<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Uang;

/**
 * Hasil penyusunan ekspor Faktur Pajak Keluaran Coretax (PRD v3.12) untuk satu periode.
 *
 * `masalahUmum` menghentikan seluruh ekspor (mis. penjual bukan PKP atau NPWP penjual belum benar). `faktur` hanya berisi
 * faktur yang lolos; yang tidak lolos tercatat di `masalahFaktur` (nomor faktur → daftar alasan) supaya pengguna tahu
 * apa yang harus dibenahi, tanpa satu faktur bermasalah menahan yang lain. `peringatan` tidak menghalangi ekspor.
 */
final readonly class HasilFakturPajakCoretax
{
    /**
     * @param  list<DataFakturPajak>  $faktur
     * @param  list<string>  $masalahUmum
     * @param  array<string, list<string>>  $masalahFaktur
     * @param  list<string>  $peringatan
     */
    public function __construct(
        public string $tinPenjual,
        public array $faktur,
        public array $masalahUmum,
        public array $masalahFaktur,
        public array $peringatan,
        public int $jumlahDiperiksa,
    ) {}

    public function BisaDiekspor(): bool
    {
        return $this->masalahUmum === [] && $this->faktur !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function KeRingkasan(): array
    {
        $dpp = Uang::Nol();
        $ppn = Uang::Nol();

        foreach ($this->faktur as $f) {
            $dpp = $dpp->Tambah(Uang::Dari($f->totalDpp));
            $ppn = $ppn->Tambah(Uang::Dari($f->totalPpn));
        }

        return [
            'JumlahDiperiksa' => $this->jumlahDiperiksa,
            'JumlahSiap' => count($this->faktur),
            'TotalDpp' => $dpp->KeString(),
            'TotalPpn' => $ppn->KeString(),
            'BisaDiekspor' => $this->BisaDiekspor(),
            'MasalahUmum' => $this->masalahUmum,
            'MasalahFaktur' => array_map(
                fn (string $nomor, array $alasan): array => ['Nomor' => $nomor, 'Alasan' => $alasan],
                array_keys($this->masalahFaktur),
                array_values($this->masalahFaktur),
            ),
            'Peringatan' => $this->peringatan,
            'Selisih' => array_values(array_filter(array_map(
                fn (DataFakturPajak $f): ?array => $f->selisihDpp === '0.00' && $f->selisihPpn === '0.00'
                    ? null
                    : ['Nomor' => $f->nomorFaktur, 'SelisihDpp' => $f->selisihDpp, 'SelisihPpn' => $f->selisihPpn],
                $this->faktur,
            ))),
        ];
    }
}
