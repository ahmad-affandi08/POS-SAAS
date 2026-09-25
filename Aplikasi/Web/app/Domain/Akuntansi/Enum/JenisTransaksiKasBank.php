<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Enum;

/**
 * Jenis dokumen `TransaksiKasBank` (F-13a, FIN-03). Akun sumber = sisi kredit, akun tujuan = sisi debit jurnal:
 * - Pengeluaran: kas/bank → beban atau aset non-kas (pengeluaran operasional, beli perlengkapan).
 * - Penerimaan: pendapatan lain/ekuitas/kewajiban/aset non-kas → kas/bank (setoran modal, pinjaman, bunga bank).
 * - Transfer: kas/bank → kas/bank (termasuk setoran brankas ke bank).
 */
enum JenisTransaksiKasBank: string
{
    case Pengeluaran = 'Pengeluaran';
    case Penerimaan = 'Penerimaan';
    case Transfer = 'Transfer';

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Pengeluaran => 'Pengeluaran',
            self::Penerimaan => 'Penerimaan',
            self::Transfer => 'Transfer',
        };
    }

    public function CekSumberKasBank(): bool
    {
        return $this !== self::Penerimaan;
    }

    public function CekTujuanKasBank(): bool
    {
        return $this !== self::Pengeluaran;
    }

    /**
     * Tipe akun lawan (bukan kas/bank) yang boleh dipakai; kosong = kedua sisi kas/bank (Transfer).
     *
     * @return list<TipeAkun>
     */
    public function AmbilTipeAkunLawan(): array
    {
        return match ($this) {
            self::Pengeluaran => [TipeAkun::Beban, TipeAkun::Aset],
            self::Penerimaan => [TipeAkun::Pendapatan, TipeAkun::Ekuitas, TipeAkun::Kewajiban, TipeAkun::Aset],
            self::Transfer => [],
        };
    }
}
