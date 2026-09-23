<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Aksi;

use App\Domain\Pengelola\Integrasi\Data\HasilUjiKoneksi;
use App\Domain\Pengelola\Integrasi\Enum\StatusIntegrasi;
use App\Domain\Pengelola\Integrasi\Model\KonfigurasiIntegrasi;
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
        $versiDiuji = $konfigurasi->DiubahPada?->toIso8601String();
        $mulai = hrtime(true);
        $hasil = app($konfigurasi->Penyedia->AmbilKelasPenguji())->Uji($konfigurasi->Pengaturan, $konfigurasi->Kredensial);
        $durasiMs = intdiv(hrtime(true) - $mulai, 1_000_000);

        return DB::transaction(function () use ($konfigurasi, $pelaku, $hasil, $durasiMs, $versiDiuji): array {
            $terkini = KonfigurasiIntegrasi::query()->lockForUpdate()->findOrFail($konfigurasi->Id);

            if ($terkini->DiubahPada?->toIso8601String() !== $versiDiuji) {
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
