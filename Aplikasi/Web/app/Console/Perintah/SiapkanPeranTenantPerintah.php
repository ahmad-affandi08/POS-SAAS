<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Organisasi\Aksi\SiapkanPeranBawaanTenant;
use App\Domain\Organisasi\Kueri\KeanggotaanPengguna;
use Illuminate\Console\Command;

/**
 * F-02: membuat/menyelaraskan peran bawaan (§19.1) di semua tenant dan memberi peran Pemilik kepada Owner yang
 * belum berperan (tenant yang terdaftar sebelum F-02). Idempoten; jalankan setelah deploy yang mengubah
 * `PeranTenantBawaan`.
 */
final class SiapkanPeranTenantPerintah extends Command
{
    protected $signature = 'organisasi:siapkan-peran';

    protected $description = 'Menyelaraskan peran bawaan tenant & menetapkan peran Pemilik untuk tenant lama (F-02).';

    public function handle(KeanggotaanPengguna $keanggotaan, SiapkanPeranBawaanTenant $siapkan): int
    {
        $daftar = $keanggotaan->AmbilSemuaIdTenant();

        foreach ($daftar as $idTenant) {
            $siapkan->Jalankan($idTenant);
        }

        $this->info(count($daftar).' tenant diselaraskan.');

        return self::SUCCESS;
    }
}
