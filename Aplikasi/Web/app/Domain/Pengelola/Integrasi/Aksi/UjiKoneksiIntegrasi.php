<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Aksi;

use App\Domain\Pengelola\Integrasi\Data\HasilUjiKoneksi;
use App\Domain\Pengelola\Integrasi\Enum\StatusIntegrasi;
use App\Domain\Pengelola\Integrasi\Model\KonfigurasiIntegrasi;
use App\Domain\Pengelola\Integrasi\Penguji\PengujiKoneksiPenyedia;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Support\Facades\DB;

/**
 * Tes koneksi satu konfigurasi (P-05 langkah 2, BR-P05.3). Panggilan jaringan dilakukan di luar transaksi; hasilnya
 * dibuang bila konfigurasi berubah selama pengujian, agar status Terhubung selalu milik isian yang benar-benar diuji.
 */
final class UjiKoneksiIntegrasi
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    /**
     * @return array{Hasil: HasilUjiKoneksi, BaruGagal: bool}
     */
    public function Jalankan(KonfigurasiIntegrasi $konfigurasi, ?PenggunaPengelola $pelaku = null): array
    {
        // Penanda isian yang diuji: isi pengaturan & kredensial, bukan waktu ubah (presisi detik).
        $isiDiuji = [$konfigurasi->Pengaturan, $konfigurasi->Kredensial];
        $mulai = hrtime(true);
        $penguji = app($konfigurasi->Penyedia->AmbilKelasPenguji());
        $hasil = $penguji instanceof PengujiKoneksiPenyedia
            ? $penguji->UjiPenyedia($konfigurasi->Penyedia->value, $konfigurasi->Pengaturan, $konfigurasi->Kredensial)
            : $penguji->Uji($konfigurasi->Pengaturan, $konfigurasi->Kredensial);
        $durasiMs = intdiv(hrtime(true) - $mulai, 1_000_000);

        return DB::transaction(function () use ($konfigurasi, $pelaku, $hasil, $durasiMs, $isiDiuji): array {
            $terkini = KonfigurasiIntegrasi::query()->lockForUpdate()->findOrFail($konfigurasi->Id);

            if ([$terkini->Pengaturan, $terkini->Kredensial] !== $isiDiuji) {
                return ['Hasil' => HasilUjiKoneksi::Gagal('Konfigurasi berubah saat diuji. Uji ulang.'), 'BaruGagal' => false];
            }

            $baruGagal = ! $hasil->berhasil && $terkini->Status !== StatusIntegrasi::Gagal;
            $terkini->update([
                'Status' => $hasil->berhasil ? StatusIntegrasi::Terhubung : StatusIntegrasi::Gagal,
                'TerakhirDiujiPada' => now(),
                'HasilUji' => ['Berhasil' => $hasil->berhasil, 'Pesan' => $hasil->pesan, 'DurasiMs' => $durasiMs],
                'GagalBeruntun' => $hasil->berhasil ? 0 : $terkini->GagalBeruntun + 1,
            ]);

            if ($pelaku !== null) {
                $this->audit->Catat(
                    'integrasi.uji',
                    $terkini,
                    nilaiBaru: ['Jenis' => $terkini->Jenis->value, 'Lingkungan' => $terkini->Lingkungan->value, 'Berhasil' => $hasil->berhasil],
                    idPelaku: $pelaku->Id,
                );
            }

            return ['Hasil' => $hasil, 'BaruGagal' => $baruGagal];
        });
    }
}
