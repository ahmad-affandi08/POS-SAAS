<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\KeanggotaanPengguna;
use App\Domain\Penjualan\Aksi\KedaluwarsakanPesananOnline;
use Illuminate\Console\Command;

/**
 * F-17 toko online: tiap lima menit menghanguskan pesanan online yang tidak pernah dikonfirmasi staf sampai
 * `PengaturanTokoOnline.MenitKedaluwarsa`. Per tenant dengan `KonteksTenant` diatur sehingga semua kueri tetap
 * lewat scope `MilikTenant`.
 */
final class KedaluwarsakanPesananOnlinePerintah extends Command
{
    protected $signature = 'pesanan-online:kedaluwarsa';

    protected $description = 'Menghanguskan pesanan online yang tidak dikonfirmasi sampai batas waktu toko (F-17).';

    public function handle(KeanggotaanPengguna $keanggotaan, KonteksTenant $konteks, KedaluwarsakanPesananOnline $kedaluwarsa): int
    {
        $sebelumnya = $konteks->Ambil();
        $jumlah = 0;

        try {
            foreach ($keanggotaan->AmbilSemuaIdTenant() as $idTenant) {
                $konteks->Atur($idTenant);
                $jumlah += $kedaluwarsa->Jalankan();
            }
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }

        $this->line("{$jumlah} pesanan online dihanguskan.");

        return self::SUCCESS;
    }
}
