<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Layanan;

use Illuminate\Support\Facades\URL;

/**
 * Tautan berhenti menerima pesan promosi CRM-07: `/berhenti-langganan/{kode}` bertanda tangan (tidak bisa ditebak
 * atau diubah), kode = `{IdTenant basis-36}.{Uuid pelanggan}` supaya halaman publik bisa memasang konteks tenant.
 */
final class TautanBerhentiLangganan
{
    public const NAMA_RUTE = 'publik.berhenti-langganan';

    public const POLA = '[0-9a-z]{1,13}\.[0-9A-HJKMNP-TV-Z]{26}';

    public static function Buat(int $idTenant, string $uuidPelanggan): string
    {
        return URL::signedRoute(self::NAMA_RUTE, ['kode' => base_convert((string) $idTenant, 10, 36).'.'.strtoupper($uuidPelanggan)]);
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
