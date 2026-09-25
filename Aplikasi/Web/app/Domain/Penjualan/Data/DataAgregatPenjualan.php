<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Angka penjualan teragregasi untuk laporan (F-14a), dihitung dari dokumen sumber:
 * - `kotor` = Σ (bruto − pajak inklusif) penjualan bukan void; `diskon` = Σ diskon baris + pesanan;
 * - `retur` = Σ nilai retur tanpa pajak & biaya layanan (dicatat pada tanggal/outlet/kasir returnya);
 * - `pajak`, `biayaLayanan`, `hpp` = bagian penjualan dikurangi bagian retur;
 * - `jumlahTransaksi` = penjualan bukan void; `jumlahRetur` = dokumen retur; `jumlahBarang` = qty satuan dasar bersih.
 * Turunan: `Bersih()` = kotor − diskon − retur (sama dengan akun Penjualan − Diskon − Retur di jurnal), `LabaKotor()`
 * = bersih − HPP, `RataRataKeranjang()` = bersih ÷ jumlah transaksi.
 */
final readonly class DataAgregatPenjualan
{
    public function __construct(
        public Uang $kotor,
        public Uang $diskon,
        public Uang $retur,
        public Uang $pajak,
        public Uang $biayaLayanan,
        public Uang $hpp,
        public int $jumlahTransaksi,
        public int $jumlahRetur = 0,
        public ?Kuantitas $jumlahBarang = null,
    ) {}

    public static function Nol(): self
    {
        return new self(Uang::Nol(), Uang::Nol(), Uang::Nol(), Uang::Nol(), Uang::Nol(), Uang::Nol(), 0);
    }

    public function Bersih(): Uang
    {
        return $this->kotor->Kurangi($this->diskon)->Kurangi($this->retur);
    }

    public function LabaKotor(): Uang
    {
        return $this->Bersih()->Kurangi($this->hpp);
    }

    public function RataRataKeranjang(): Uang
    {
        if ($this->jumlahTransaksi === 0) {
            return Uang::Nol();
        }

        return Uang::Dari(BigDecimal::of($this->Bersih()->KeString())->dividedBy($this->jumlahTransaksi, Uang::SKALA, RoundingMode::HalfUp));
    }

    public function Tambah(self $lain): self
    {
        $barang = $this->jumlahBarang === null && $lain->jumlahBarang === null
            ? null
            : ($this->jumlahBarang ?? Kuantitas::Nol())->Tambah($lain->jumlahBarang ?? Kuantitas::Nol());

        return new self(
            $this->kotor->Tambah($lain->kotor),
            $this->diskon->Tambah($lain->diskon),
            $this->retur->Tambah($lain->retur),
            $this->pajak->Tambah($lain->pajak),
            $this->biayaLayanan->Tambah($lain->biayaLayanan),
            $this->hpp->Tambah($lain->hpp),
            $this->jumlahTransaksi + $lain->jumlahTransaksi,
            $this->jumlahRetur + $lain->jumlahRetur,
            $barang,
        );
    }

    /**
     * Angka sebagai string desimal (kunci JSON = nama kolom laporan).
     *
     * @return array{Kotor: string, Diskon: string, Retur: string, Bersih: string, Pajak: string, BiayaLayanan: string, Hpp: string, LabaKotor: string, JumlahTransaksi: int, JumlahRetur: int, RataRataKeranjang: string, JumlahBarang: string|null}
     */
    public function KeLarik(): array
    {
        return [
            'Kotor' => $this->kotor->KeString(),
            'Diskon' => $this->diskon->KeString(),
            'Retur' => $this->retur->KeString(),
            'Bersih' => $this->Bersih()->KeString(),
            'Pajak' => $this->pajak->KeString(),
            'BiayaLayanan' => $this->biayaLayanan->KeString(),
            'Hpp' => $this->hpp->KeString(),
            'LabaKotor' => $this->LabaKotor()->KeString(),
            'JumlahTransaksi' => $this->jumlahTransaksi,
            'JumlahRetur' => $this->jumlahRetur,
            'RataRataKeranjang' => $this->RataRataKeranjang()->KeString(),
            'JumlahBarang' => $this->jumlahBarang?->KeString(),
        ];
    }
}
