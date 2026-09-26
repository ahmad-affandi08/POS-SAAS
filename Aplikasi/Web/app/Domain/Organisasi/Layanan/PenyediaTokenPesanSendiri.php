<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Layanan;

use App\Domain\Organisasi\Model\Meja;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * F-17: token QR pesan sendiri per meja: 32 karakter acak alfanumerik (aman di URL, ~190 bit). Dibuat saat QR meja
 * pertama kali ditampilkan di back-office (tanpa audit karena belum ada URL yang beredar); diganti lewat
 * `BuatUlangTokenPesanSendiri`.
 */
final class PenyediaTokenPesanSendiri
{
    public const PANJANG = 32;

    public function Pastikan(Meja $meja): string
    {
        if ($meja->TokenPesanSendiri !== null) {
            return $meja->TokenPesanSendiri;
        }

        return DB::transaction(function () use ($meja): string {
            $baris = Meja::query()->lockForUpdate()->findOrFail($meja->Id);

            if ($baris->TokenPesanSendiri === null) {
                $baris->forceFill(['TokenPesanSendiri' => self::BuatToken()])->save();
            }

            $meja->TokenPesanSendiri = $baris->TokenPesanSendiri;

            return (string) $baris->TokenPesanSendiri;
        });
    }

    public static function BuatToken(): string
    {
        return Str::random(self::PANJANG);
    }
}
