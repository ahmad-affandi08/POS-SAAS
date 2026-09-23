<?php

declare(strict_types=1);

namespace App\Http\Perantara;

/**
 * Kunci sesi autentikasi tenant (F-00, BR-00.8). Masuk dengan 2FA berlangsung dua langkah: setelah kata sandi benar,
 * pengguna **belum** masuk; hanya Id-nya yang disimpan sebagai "masuk tertunda" sampai kode 2FA terverifikasi.
 */
final class SesiAutentikasiTenant
{
    public const MASUK_TERTUNDA_ID = 'Autentikasi.MasukTertunda.IdPengguna';

    public const MASUK_TERTUNDA_INGAT = 'Autentikasi.MasukTertunda.Ingat';

    public const MASUK_TERTUNDA_SAMPAI = 'Autentikasi.MasukTertunda.Sampai';

    /** Batas waktu langkah kedua; lewat dari ini pengguna mengulang dari kata sandi. */
    public const MENIT_MASUK_TERTUNDA = 10;

    public const RAHASIA_2FA_SEMENTARA = 'Autentikasi.Rahasia2faSementara';

    public const KODE_PEMULIHAN_BARU = 'Autentikasi.KodePemulihanBaru';
}
