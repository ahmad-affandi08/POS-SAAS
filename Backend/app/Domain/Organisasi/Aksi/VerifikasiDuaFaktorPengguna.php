<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Layanan\DuaFaktorPengguna;
use App\Domain\Organisasi\Model\Pengguna;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Langkah kedua masuk untuk akun ber-2FA (§20.2, BR-00.8): kode TOTP 6 digit, atau kode pemulihan sekali pakai.
 * Kode TOTP yang sudah dipakai tidak bisa dipakai ulang selama masa berlakunya (mencegah replay); kode pemulihan
 * dipakai di bawah kunci baris sehingga satu kode tidak lolos dua kali walau dikirim bersamaan.
 */
final class VerifikasiDuaFaktorPengguna
{
    /** Jendela verifikasi ±1 langkah 30 detik → kode hidup paling lama ±90 detik. */
    private const DETIK_TANDA_TERPAKAI = 120;

    public function __construct(private readonly DuaFaktorPengguna $duaFaktor) {}

    public function Jalankan(Pengguna $pengguna, string $kode): void
    {
        if (! $pengguna->CekDuaFaktorAktif() || $pengguna->Rahasia2fa === null) {
            throw new PelanggaranAturanBisnis('DuaFaktorBelumAktif', 'Verifikasi dua langkah belum aktif untuk akun ini.');
        }

        $kodeNormal = preg_replace('/\s+/', '', $kode) ?? '';

        if ($this->duaFaktor->VerifikasiKode($pengguna->Rahasia2fa, $kodeNormal)) {
            if (! Cache::add('dua-faktor-terpakai:'.$pengguna->Id.':'.$kodeNormal, true, self::DETIK_TANDA_TERPAKAI)) {
                throw new PelanggaranAturanBisnis('KodeDuaFaktorTerpakai', 'Kode ini baru saja dipakai. Tunggu kode berikutnya di aplikasi autentikator.', 'Kode');
            }

            return;
        }

        // Kode pemulihan sekali pakai: baris pengguna dikunci agar dua permintaan bersamaan tidak memakai kode yang sama.
        $kodePemulihan = Str::upper(trim($kode));
        $terpakai = DB::transaction(function () use ($pengguna, $kodePemulihan): bool {
            $terkunci = Pengguna::query()->whereKey($pengguna->Id)->lockForUpdate()->first();

            if ($terkunci === null) {
                return false;
            }

            $sisa = $terkunci->KodePemulihan2fa ?? [];
            $cocok = array_values(array_filter($sisa, fn (string $simpanan) => hash_equals($simpanan, $kodePemulihan)));

            if ($cocok === []) {
                return false;
            }

            $terkunci->forceFill(['KodePemulihan2fa' => array_values(array_diff($sisa, $cocok))])->save();
            $pengguna->setRawAttributes($terkunci->getAttributes(), true);

            return true;
        });

        if ($terpakai) {
            return;
        }

        throw new PelanggaranAturanBisnis('KodeDuaFaktorSalah', 'Kode tidak cocok. Masukkan kode terbaru dari aplikasi autentikator atau kode pemulihan.', 'Kode');
    }
}
