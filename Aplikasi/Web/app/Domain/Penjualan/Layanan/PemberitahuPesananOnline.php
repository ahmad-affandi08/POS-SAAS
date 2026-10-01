<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Penjualan\Enum\PeristiwaPesananOnline;
use App\Domain\Penjualan\Model\NotifikasiPesananOnline;
use App\Domain\Penjualan\Model\PengaturanTokoOnline;
use App\Domain\Penjualan\Model\PesananOnline;
use App\Domain\Penjualan\Tugas\KirimNotifikasiPesananOnlineTugas;

/**
 * F-17 bagian 3 (v3.32): antrekan pemberitahuan WhatsApp status pesanan ke pembeli. Dipanggil **di dalam** transaksi
 * yang mengubah status; barisnya ikut batal bila transaksinya batal, dan tugas kirim baru jalan setelah commit.
 * Satu pesanan hanya mendapat satu pesan per peristiwa (kunci unik), jadi status yang bolak-balik tidak membuat
 * pembeli menerima pesan ganda. Pesan bersifat transaksional (pembeli memberikan nomor & persetujuan untuk pesanan
 * ini), bukan promosi, sehingga tidak bergantung `SetujuPemasaran`.
 */
final class PemberitahuPesananOnline
{
    public function Antrekan(PesananOnline $pesanan, PeristiwaPesananOnline $peristiwa): void
    {
        if (trim((string) $pesanan->NoHp) === '' || ! (PengaturanTokoOnline::query()->value('NotifikasiWhatsappAktif') ?? true)) {
            return;
        }

        $baris = NotifikasiPesananOnline::query()->firstOrCreate(
            ['IdPesananOnline' => $pesanan->Id, 'Peristiwa' => $peristiwa->value],
        );

        if ($baris->wasRecentlyCreated) {
            KirimNotifikasiPesananOnlineTugas::dispatch($baris->IdTenant, $baris->Id)->afterCommit();
        }
    }
}
