<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

/**
 * Kode struk digital `/s/{kodeStruk}` (POS-11): `{IdTenant basis-36}.{Uuid penjualan}`. Bisa disusun aplikasi kasir
 * saat offline dari awalan yang dikirim `data-awal` (`Struk.AwalanStrukDigital`) ditambah UuidKlien penjualan.
 * Bagian tenant hanya menetapkan scope pencarian (seperti device token); rahasianya ada di bagian acak ULID.
 */
final class KodeStrukDigital
{
    public const POLA = '[0-9a-z]{1,13}\.[0-9A-HJKMNP-TV-Z]{26}';

    public static function AmbilAwalan(int $idTenant): string
    {
        return url('/s/'.base_convert((string) $idTenant, 10, 36).'.');
    }

    public static function Buat(int $idTenant, string $uuidPenjualan): string
    {
        return base_convert((string) $idTenant, 10, 36).'.'.strtoupper($uuidPenjualan);
    }

    /**
     * @return array{IdTenant: int, Uuid: string}|null
     */
    public static function Urai(string $kode): ?array
    {
        if (preg_match('/^'.self::POLA.'$/', $kode) !== 1) {
            return null;
        }

        [$tenant, $uuid] = explode('.', $kode, 2);
        $idTenant = (int) base_convert($tenant, 36, 10);

        return $idTenant > 0 ? ['IdTenant' => $idTenant, 'Uuid' => $uuid] : null;
    }
}
