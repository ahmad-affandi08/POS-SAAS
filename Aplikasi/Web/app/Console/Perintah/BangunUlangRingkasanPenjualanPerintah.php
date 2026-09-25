<?php

declare(strict_types=1);

namespace App\Console\Perintah;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Laporan\Aksi\BangunUlangRingkasanPenjualanHarian;
use App\Domain\Organisasi\Kueri\KeanggotaanPengguna;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * F-14a: membangun ulang `RingkasanPenjualanHarian` dari dokumen sumber untuk semua tenant (atau `--tenant`).
 * `tanggal` (`YYYY-MM-DD`) kosong = H-1 dan H-2 menurut zona waktu tenant (dijadwalkan tiap malam di
 * `routes/console.php`) untuk menangkap penjualan offline yang terlambat tersinkron atau job antrean yang gagal.
 * Tenant diproses dengan `KonteksTenant` diatur sehingga semua kueri tetap lewat scope `MilikTenant`.
 */
final class BangunUlangRingkasanPenjualanPerintah extends Command
{
    protected $signature = 'laporan:bangun-ulang-ringkasan {tanggal? : Tanggal bisnis YYYY-MM-DD (kosong = H-1 & H-2)} {--tenant=* : Id tenant (kosong = semua)}';

    protected $description = 'Membangun ulang ringkasan penjualan harian dari dokumen penjualan, void, dan retur (F-14a).';

    public function handle(
        KeanggotaanPengguna $keanggotaan,
        KonteksTenant $konteks,
        TanggalBisnisOutlet $tanggalBisnis,
        BangunUlangRingkasanPenjualanHarian $bangunUlang,
    ): int {
        $tanggal = $this->argument('tanggal');
        $tanggalDiminta = null;

        if (is_string($tanggal) && $tanggal !== '') {
            $tanggalDiminta = CarbonImmutable::createFromFormat('!Y-m-d', $tanggal);

            if (! $tanggalDiminta instanceof CarbonImmutable || $tanggalDiminta->format('Y-m-d') !== $tanggal) {
                $this->error("Tanggal tidak valid: {$tanggal} (format YYYY-MM-DD).");

                return self::FAILURE;
            }
        }

        $diminta = [];

        foreach ((array) $this->option('tenant') as $nilai) {
            $teks = is_scalar($nilai) ? trim((string) $nilai) : '';

            if (preg_match('/^[1-9]\d*$/', $teks) !== 1) {
                $this->error('Id tenant tidak valid: '.($teks === '' ? '(kosong)' : $teks));

                return self::FAILURE;
            }

            $diminta[] = (int) $teks;
        }

        $semua = $keanggotaan->AmbilSemuaIdTenant();
        $daftar = $diminta === [] ? $semua : array_values(array_intersect(array_unique($diminta), $semua));
        $sebelumnya = $konteks->Ambil();
        $jumlahBaris = 0;

        try {
            foreach ($daftar as $idTenant) {
                $konteks->Atur($idTenant);
                $hariIni = $tanggalBisnis->Hitung(null);
                $daftarTanggal = $tanggalDiminta !== null ? [$tanggalDiminta] : [$hariIni->subDay(), $hariIni->subDays(2)];

                foreach ($daftarTanggal as $t) {
                    $jumlahBaris += $bangunUlang->Jalankan(CarbonImmutable::parse($t->toDateString()));
                }
            }
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }

        $this->line(count($daftar)." tenant diproses, {$jumlahBaris} baris ringkasan ditulis.");

        return self::SUCCESS;
    }
}
