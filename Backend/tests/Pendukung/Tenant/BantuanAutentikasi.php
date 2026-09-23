<?php

declare(strict_types=1);

namespace Tests\Pendukung\Tenant;

use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Tenant\Enum\JenisDokumenLegal;
use App\Domain\Tenant\Enum\StatusDokumenLegal;
use App\Domain\Tenant\Model\DokumenLegal;
use PragmaRX\Google2FA\Google2FA;

/**
 * Bantuan test autentikasi tenant: 2FA (BR-00.8) dan dokumen legal materiil (BR-P06.5).
 */
final class BantuanAutentikasi
{
    public const KATA_SANDI = 'kata-sandi-kuat-123';

    /** Mengaktifkan 2FA langsung di data (untuk test yang tidak menguji alur aktivasi). */
    public static function AktifkanDuaFaktor(Pengguna $pengguna): string
    {
        $rahasia = (new Google2FA)->generateSecretKey(32);
        $pengguna->forceFill([
            'Rahasia2fa' => $rahasia,
            'KodePemulihan2fa' => ['AAAAA-BBBBB', 'CCCCC-DDDDD'],
            'DuaFaktorAktifPada' => now(),
        ])->save();

        return $rahasia;
    }

    public static function KodeSaatIni(string $rahasia): string
    {
        return (new Google2FA)->getCurrentOtp($rahasia);
    }

    /** Kode TOTP yang pasti salah (bukan kode saat ini, sebelum, atau sesudahnya). */
    public static function KodeSalah(string $rahasia): string
    {
        $google2fa = new Google2FA;

        do {
            $kode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while ($google2fa->verifyKey($rahasia, $kode, 2));

        return $kode;
    }

    public static function TerbitkanVersi(
        JenisDokumenLegal $jenis,
        int $versi,
        string $berlakuMulai,
        bool $materiil = true,
    ): DokumenLegal {
        return DokumenLegal::query()->create([
            'Jenis' => $jenis,
            'Versi' => $versi,
            'Judul' => "{$jenis->AmbilLabel()} v{$versi}",
            'Isi' => "# {$jenis->AmbilLabel()} versi {$versi}",
            'RingkasanPerubahan' => $materiil ? 'Perubahan dasar pemrosesan data pelanggan tenant.' : null,
            'Materiil' => $materiil,
            'BerlakuMulai' => $berlakuMulai,
            'Status' => StatusDokumenLegal::Terbit,
            'DiterbitkanPada' => now(),
        ]);
    }
}
