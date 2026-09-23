<?php

declare(strict_types=1);

namespace App\Domain\PanduanAwal\Data;

/**
 * Ringkasan penerapan template sektor (F-01 langkah 2) untuk pesan ke pemilik. Semua jumlah = data baru saja.
 */
final readonly class HasilPenerapanTemplate
{
    public function __construct(
        public string $kodeTemplate,
        public string $namaTemplate,
        public int $versi,
        public int $jumlahAkun,
        public int $jumlahPemetaan,
        public int $jumlahKategori,
        public int $jumlahSatuan,
        public int $jumlahKelompokPajak,
        public int $jumlahFitur,
        public int $pengaturanDitambahkan,
        public bool $versiBerubah,
    ) {}

    public function CekAdaPerubahan(): bool
    {
        return $this->versiBerubah || $this->jumlahAkun + $this->jumlahPemetaan + $this->jumlahKategori + $this->jumlahSatuan
            + $this->jumlahKelompokPajak + $this->jumlahFitur + $this->pengaturanDitambahkan > 0;
    }
}
