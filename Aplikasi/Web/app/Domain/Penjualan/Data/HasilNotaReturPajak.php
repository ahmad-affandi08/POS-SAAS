<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Uang;

/**
 * Hasil penyusunan rekap nota retur pajak (PRD v4.08) satu periode: `retur` yang siap dilaporkan, `masalahRetur`
 * (nomor retur → alasan) yang belum bisa, `masalahUmum` yang menahan semuanya (penjual bukan PKP/NPWP belum benar).
 */
final readonly class HasilNotaReturPajak
{
    /**
     * @param  list<DataNotaReturPajak>  $retur
     * @param  list<string>  $masalahUmum
     * @param  array<string, list<string>>  $masalahRetur
     * @param  list<string>  $peringatan
     */
    public function __construct(
        public array $retur,
        public array $masalahUmum,
        public array $masalahRetur,
        public array $peringatan,
        public int $jumlahDiperiksa,
    ) {}

    public function BisaDiekspor(): bool
    {
        return $this->masalahUmum === [] && $this->retur !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function KeRingkasan(): array
    {
        $dpp = Uang::Nol();
        $ppn = Uang::Nol();

        foreach ($this->retur as $r) {
            $dpp = $dpp->Tambah(Uang::Dari($r->totalDpp));
            $ppn = $ppn->Tambah(Uang::Dari($r->totalPpn));
        }

        return [
            'JumlahDiperiksa' => $this->jumlahDiperiksa,
            'JumlahSiap' => count($this->retur),
            'TotalDpp' => $dpp->KeString(),
            'TotalPpn' => $ppn->KeString(),
            'BisaDiekspor' => $this->BisaDiekspor(),
            'MasalahUmum' => $this->masalahUmum,
            'MasalahRetur' => array_map(
                fn (string $nomor, array $alasan): array => ['Nomor' => $nomor, 'Alasan' => $alasan],
                array_keys($this->masalahRetur),
                array_values($this->masalahRetur),
            ),
            'Peringatan' => $this->peringatan,
            'Retur' => array_map(fn (DataNotaReturPajak $r): array => [
                'Nomor' => $r->nomorRetur,
                'Tanggal' => $r->tanggalRetur,
                'NomorFaktur' => $r->nomorFaktur,
                'NomorFakturPajak' => $r->nomorFakturPajak,
                'Pembeli' => $r->namaPembeli,
                'Dpp' => $r->totalDpp,
                'Ppn' => $r->totalPpn,
            ], $this->retur),
        ];
    }

    /**
     * Baris CSV per barang (satu nota retur bisa beberapa baris).
     *
     * @return array{0: list<string>, 1: list<list<string>>}
     */
    public function KeCsv(): array
    {
        $judul = [
            'Nomor retur', 'Tanggal retur', 'Nomor faktur', 'Tanggal faktur', 'Nomor Faktur Pajak', 'Nama pembeli',
            'Jenis identitas', 'NPWP pembeli', 'NIK pembeli', 'Alasan retur', 'Barang/Jasa', 'Kode barang/jasa',
            'Kode satuan', 'Harga satuan', 'Jumlah', 'Diskon', 'DPP', 'DPP nilai lain', 'Tarif PPN (%)', 'PPN',
        ];
        $baris = [];

        foreach ($this->retur as $r) {
            foreach ($r->baris as $b) {
                $baris[] = [
                    $r->nomorRetur, $r->tanggalRetur, $r->nomorFaktur, $r->tanggalFaktur, $r->nomorFakturPajak, $r->namaPembeli,
                    $r->jenisDokumenPembeli === 'TIN' ? 'NPWP' : 'NIK', $r->jenisDokumenPembeli === 'TIN' ? $r->tinPembeli : '', $r->nomorDokumenPembeli,
                    $r->alasan, $b->nama, $b->kode, $b->satuan, $b->harga, $b->jumlah, $b->totalDiskon, $b->dpp, $b->dppNilaiLain, $b->tarifPpn, $b->ppn,
                ];
            }
        }

        return [$judul, $baris];
    }
}
