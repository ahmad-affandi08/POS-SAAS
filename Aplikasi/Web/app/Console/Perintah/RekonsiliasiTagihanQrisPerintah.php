<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\KeanggotaanPengguna;
use App\Domain\Penjualan\Aksi\RekonsiliasiTagihanQris;
use Illuminate\Console\Command;

/**
 * Audit P0 F-02: tiap 10 menit menyelesaikan tagihan QRIS dinamis yang hasil pembuatannya di gerbang tidak pasti.
 * Per tenant dengan `KonteksTenant` diatur sehingga semua kueri tetap lewat scope `MilikTenant`.
 */
final class RekonsiliasiTagihanQrisPerintah extends Command
{
    protected $signature = 'penjualan:rekonsiliasi-qris';

    protected $description = 'Merekonsiliasi tagihan QRIS dinamis berstatus TidakPasti dengan gerbang pembayaran (audit P0 F-02).';

    public function handle(KeanggotaanPengguna $keanggotaan, KonteksTenant $konteks, RekonsiliasiTagihanQris $rekonsiliasi): int
    {
        $sebelumnya = $konteks->Ambil();
        $berubah = 0;

        try {
            foreach ($keanggotaan->AmbilSemuaIdTenant() as $idTenant) {
                $konteks->Atur($idTenant);
                $berubah += $rekonsiliasi->Jalankan();
            }
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }

        $this->line("{$berubah} tagihan QRIS tidak pasti diselesaikan.");

        return self::SUCCESS;
    }
}
