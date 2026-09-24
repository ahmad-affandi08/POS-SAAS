<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\KeanggotaanPengguna;
use App\Domain\Persediaan\Layanan\PembangunUlangSaldoStok;
use App\Domain\Persediaan\Layanan\PemeriksaKonsistensiStok;
use Illuminate\Console\Command;

/**
 * Membangun ulang / memeriksa saldo stok semua tenant (DesainF05a C.9). `--periksa` keluar dengan kode 1 bila ada
 * perbedaan.
 *
 * Tenant diambil dari `KeanggotaanPengguna::AmbilSemuaIdTenant()` (atau `--tenant`), dan untuk tiap tenant
 * `KonteksTenant` diatur sehingga semua kueri tetap lewat scope `MilikTenant` (tanpa melewati scope).
 * - `--periksa`: hanya melaporkan (`PemeriksaKonsistensiStok`); kode keluar 1 bila ada perbedaan. Dijadwalkan
 *   tiap malam di `routes/console.php`.
 * - tanpa `--periksa`: memperbaiki cache (`PembangunUlangSaldoStok`, LogAudit per tenant bila ada perbaikan),
 *   lalu memeriksa lagi; perbedaan yang tersisa (rantai mutasi, lapisan FIFO, nomor seri) tidak bisa diperbaiki
 *   perintah ini dan membuat kode keluar 1.
 */
final class BangunUlangSaldoStokPerintah extends Command
{
    protected $signature = 'persediaan:bangun-ulang-saldo {--tenant=* : Id tenant (kosong = semua)} {--periksa : Hanya memeriksa, keluar 1 bila berbeda}';

    protected $description = 'Membangun ulang atau memeriksa SaldoStok dari MutasiStok (F-05a).';

    public function handle(
        KeanggotaanPengguna $keanggotaan,
        KonteksTenant $konteks,
        PembangunUlangSaldoStok $pembangun,
        PemeriksaKonsistensiStok $pemeriksa,
    ): int {
        $semua = $keanggotaan->AmbilSemuaIdTenant();
        $diminta = $this->AmbilTenantDiminta();

        if ($diminta === null) {
            return self::FAILURE;
        }

        $daftar = $diminta === [] ? $semua : array_values(array_intersect($diminta, $semua));

        foreach (array_diff($diminta, $semua) as $idTidakAda) {
            $this->warn("Tenant {$idTidakAda} tidak ditemukan.");
        }

        $hanyaPeriksa = (bool) $this->option('periksa');
        $tenantBermasalah = 0;
        $totalDiperbaiki = 0;
        $konteksSebelumnya = $konteks->Ambil();

        try {
            foreach ($daftar as $idTenant) {
                $konteks->Atur($idTenant);

                if (! $hanyaPeriksa) {
                    $diperbaiki = $pembangun->Jalankan();
                    $totalDiperbaiki += $diperbaiki;

                    if ($diperbaiki > 0) {
                        $this->info("Tenant {$idTenant}: {$diperbaiki} saldo stok diperbaiki.");
                    }
                }

                $perbedaan = $pemeriksa->Periksa();

                if ($perbedaan === []) {
                    continue;
                }

                $tenantBermasalah++;
                $this->error("Tenant {$idTenant}: ".count($perbedaan).' perbedaan'.($hanyaPeriksa ? '.' : ' yang tidak bisa diperbaiki otomatis.'));

                foreach ($perbedaan as $uraian) {
                    $this->line("  - {$uraian}");
                }
            }
        } finally {
            $konteksSebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($konteksSebelumnya);
        }

        $jumlahTenant = count($daftar);

        if ($hanyaPeriksa) {
            $this->line($tenantBermasalah === 0
                ? "{$jumlahTenant} tenant diperiksa, semua konsisten."
                : "{$jumlahTenant} tenant diperiksa, {$tenantBermasalah} tenant berbeda. Jalankan tanpa --periksa untuk membangun ulang saldo.");
        } else {
            $this->line("{$jumlahTenant} tenant diproses, {$totalDiperbaiki} saldo stok diperbaiki.");
        }

        return $tenantBermasalah === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<int>|null null bila ada Id yang tidak valid
     */
    private function AmbilTenantDiminta(): ?array
    {
        $opsi = $this->option('tenant');
        $hasil = [];

        foreach (is_array($opsi) ? $opsi : [] as $nilai) {
            $teks = is_scalar($nilai) ? trim((string) $nilai) : '';

            if (preg_match('/^[1-9]\d*$/', $teks) !== 1) {
                $this->error('Id tenant tidak valid: '.($teks === '' ? '(kosong)' : $teks));

                return null;
            }

            $hasil[] = (int) $teks;
        }

        return array_values(array_unique($hasil));
    }
}
