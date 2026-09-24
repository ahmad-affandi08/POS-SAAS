<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan\Hpp;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use Brick\Math\BigDecimal;

/**
 * Keadaan HPP satu (produk, lokasi stok) selama satu dokumen mutasi (DesainF05a C.3): Q = `jumlah`
 * (= SaldoStok.JumlahTersedia), N = `nilai` (= NilaiPersediaan), A = `hppRataRata` (null = HPP belum diketahui).
 * FIFO: `lapisan` = lapisan terbuka urut Id (lapisan baru dokumen ini ditambahkan di belakang) dan
 * `hppLapisanTerakhir` = HPP lapisan terbaru (terbuka maupun habis) untuk menilai stok minus.
 */
final class KeadaanHpp
{
    /**
     * @param  list<LapisanHpp>  $lapisan
     */
    public function __construct(
        public Kuantitas $jumlah,
        public Uang $nilai,
        public ?BigDecimal $hppRataRata = null,
        public array $lapisan = [],
        public ?BigDecimal $hppLapisanTerakhir = null,
    ) {}

    public static function BuatKosong(): self
    {
        return new self(Kuantitas::Nol(), Uang::Nol());
    }

    /**
     * Lapisan yang masih bersisa urut Id, opsional hanya milik satu batch.
     *
     * @return list<LapisanHpp>
     */
    public function AmbilLapisanTerbuka(?int $idBatchStok = null): array
    {
        return array_values(array_filter(
            $this->lapisan,
            fn (LapisanHpp $l): bool => ! $l->CekHabis() && ($idBatchStok === null || $l->idBatchStok === $idBatchStok),
        ));
    }
}
