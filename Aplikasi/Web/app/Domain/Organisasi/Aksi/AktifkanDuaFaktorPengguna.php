<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Layanan\DuaFaktorPengguna;
use App\Domain\Organisasi\Layanan\PencatatAuditAkun;
use App\Domain\Organisasi\Model\Pengguna;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mengaktifkan 2FA TOTP akun tenant setelah kode pertama terbukti benar (§20.2, BR-00.8). Token "ingat saya" diganti
 * agar perangkat lain yang masuk tanpa 2FA harus masuk ulang. Dicatat di `LogAudit` setiap tenant anggota (§25 no. 17).
 */
final class AktifkanDuaFaktorPengguna
{
    public function __construct(
        private readonly DuaFaktorPengguna $duaFaktor,
        private readonly PencatatAuditAkun $auditAkun,
    ) {}

    /**
     * @return list<string> kode pemulihan, ditampilkan sekali saja
     */
    public function Jalankan(Pengguna $pengguna, string $rahasia, string $kode): array
    {
        if ($pengguna->CekDuaFaktorAktif()) {
            throw new PelanggaranAturanBisnis('DuaFaktorSudahAktif', 'Verifikasi dua langkah sudah aktif untuk akun ini.');
        }

        if (! $this->duaFaktor->VerifikasiKode($rahasia, $kode)) {
            throw new PelanggaranAturanBisnis('KodeDuaFaktorSalah', 'Kode tidak cocok. Periksa jam di ponsel lalu masukkan kode terbaru.', 'Kode');
        }

        $kodePemulihan = $this->duaFaktor->BuatKodePemulihan();

        DB::transaction(function () use ($pengguna, $rahasia, $kodePemulihan): void {
            $pengguna->forceFill([
                'Rahasia2fa' => $rahasia,
                'KodePemulihan2fa' => $kodePemulihan,
                'DuaFaktorAktifPada' => now(),
                'TokenIngat' => Str::random(60),
            ])->save();
            $this->auditAkun->Catat('akun.dua-faktor-aktif', $pengguna);
        });

        return $kodePemulihan;
    }
}
