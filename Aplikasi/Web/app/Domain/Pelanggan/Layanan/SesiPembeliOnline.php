<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Layanan;

use App\Domain\Pelanggan\Enum\StatusPelanggan;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\SesiPelangganOnline;
use Carbon\CarbonImmutable;

/**
 * F-17 bagian 3: sesi masuk pembeli toko online. Token acak disimpan di cookie (HttpOnly, SameSite=Lax, path toko);
 * server hanya menyimpan hash-nya di `SesiPelangganOnline`, berlaku 30 hari sejak masuk. Sesi yang dicabut, kedaluwarsa,
 * milik tenant lain (scope `MilikTenant`), atau pelanggannya diarsipkan dianggap tidak masuk.
 */
final class SesiPembeliOnline
{
    public const NAMA_COOKIE = 'pembeli_toko';

    public const HARI_BERLAKU = 30;

    /** Token untuk cookie; hanya hash-nya yang disimpan. */
    public function BukaSesi(Pelanggan $pelanggan): string
    {
        $token = SandiAkunOnline::BuatToken();
        SesiPelangganOnline::query()->create([
            'IdPelanggan' => $pelanggan->Id,
            'HashToken' => SandiAkunOnline::BuatHash($token),
            'KedaluwarsaPada' => CarbonImmutable::now()->addDays(self::HARI_BERLAKU),
            'TerakhirDipakaiPada' => CarbonImmutable::now(),
        ]);

        return $token;
    }

    public function CariPelanggan(mixed $token): ?Pelanggan
    {
        $sesi = $this->CariSesi($token);

        if ($sesi === null) {
            return null;
        }

        $pelanggan = Pelanggan::query()->whereKey($sesi->IdPelanggan)->where('Status', StatusPelanggan::Aktif->value)->first();

        // Jejak pemakaian cukup per 5 menit; menulis setiap muat halaman hanya membebani basis data.
        if ($pelanggan !== null && ($sesi->TerakhirDipakaiPada === null || $sesi->TerakhirDipakaiPada->lessThan(now()->subMinutes(5)))) {
            $sesi->forceFill(['TerakhirDipakaiPada' => now()])->save();
        }

        return $pelanggan;
    }

    public function TutupSesi(mixed $token): void
    {
        $this->CariSesi($token)?->forceFill(['DicabutPada' => now()])->save();
    }

    /** Keluar dari semua perangkat: semua sesi aktif pelanggan ini dicabut. */
    public function TutupSemuaSesi(int $idPelanggan): void
    {
        SesiPelangganOnline::query()->where('IdPelanggan', $idPelanggan)->whereNull('DicabutPada')->update(['DicabutPada' => now()]);
    }

    private function CariSesi(mixed $token): ?SesiPelangganOnline
    {
        if (! is_string($token) || preg_match('/^[0-9a-f]{48}$/', $token) !== 1) {
            return null;
        }

        return SesiPelangganOnline::query()
            ->where('HashToken', SandiAkunOnline::BuatHash($token))
            ->whereNull('DicabutPada')
            ->where('KedaluwarsaPada', '>', now())
            ->first();
    }
}
