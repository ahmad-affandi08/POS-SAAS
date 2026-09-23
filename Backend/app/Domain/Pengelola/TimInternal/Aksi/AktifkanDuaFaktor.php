<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TimInternal\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\DuaFaktorPengelola;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Support\Facades\DB;

/**
 * P-01 langkah 4 (bagian 2): mengaktifkan 2FA TOTP setelah kode pertama terbukti benar (BR-P01.2).
 */
final class AktifkanDuaFaktor
{
    public function __construct(
        private readonly DuaFaktorPengelola $duaFaktor,
        private readonly PencatatAuditPengelola $audit,
    ) {}

    /**
     * @return list<string> kode pemulihan, ditampilkan sekali saja
     */
    public function Jalankan(PenggunaPengelola $pengguna, string $rahasia, string $kode): array
    {
        if ($pengguna->DuaFaktorAktif()) {
            throw new PelanggaranAturanBisnis('DuaFaktorSudahAktif', 'Verifikasi dua langkah sudah aktif untuk akun ini.');
        }

        if (! $this->duaFaktor->VerifikasiKode($rahasia, $kode)) {
            throw new PelanggaranAturanBisnis('KodeDuaFaktorSalah', 'Kode tidak cocok. Periksa jam di ponsel lalu masukkan kode terbaru.', 'Kode');
        }

        $kodePemulihan = $this->duaFaktor->BuatKodePemulihan();

        DB::transaction(function () use ($pengguna, $rahasia, $kodePemulihan): void {
            $pengguna->update([
                'Rahasia2fa' => $rahasia,
                'KodePemulihan2fa' => $kodePemulihan,
                'DuaFaktorAktifPada' => now(),
            ]);

            $this->audit->Catat('sesi.dua-faktor.aktifkan', $pengguna, idPelaku: $pengguna->Id);
        });

        return $kodePemulihan;
    }
}
