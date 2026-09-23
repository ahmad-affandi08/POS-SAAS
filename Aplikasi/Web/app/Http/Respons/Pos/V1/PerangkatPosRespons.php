<?php

declare(strict_types=1);

namespace App\Http\Respons\Pos\V1;

use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Organisasi\Model\Perangkat;
use App\Domain\Tenant\Enum\StatusLangganan;
use Illuminate\Support\Carbon;

/**
 * Bentuk JSON API POS v1 untuk perangkat, outlet, tenant, dan langganan (PRD §16.2: key = nama kolom PascalCase,
 * tanggal ISO-8601 UTC). Bagian kontrak `/api/pos/v1`: menambah key boleh, mengubah/menghapus key = versi baru.
 */
final class PerangkatPosRespons
{
    /**
     * @return array<string, mixed>
     */
    public static function Perangkat(Perangkat $perangkat): array
    {
        return [
            'Uuid' => $perangkat->Uuid,
            'Kode' => $perangkat->Kode,
            'Nama' => $perangkat->Nama,
            'Jenis' => $perangkat->Jenis->value,
            'Platform' => $perangkat->Platform?->value,
            'DiaktifkanPada' => self::Waktu($perangkat->DiaktifkanPada),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function Outlet(Outlet $outlet): array
    {
        return [
            'Uuid' => $outlet->Uuid,
            'Kode' => $outlet->Kode,
            'Nama' => $outlet->Nama,
            'Alamat' => $outlet->Alamat,
            'ZonaWaktu' => $outlet->ZonaWaktu,
            'JamTutupBuku' => $outlet->JamTutupBuku,
        ];
    }

    /**
     * @param  array{Uuid: string, Nama: string}|null  $tenant
     * @return array<string, mixed>|null
     */
    public static function Tenant(?array $tenant): ?array
    {
        return $tenant === null ? null : ['Uuid' => $tenant['Uuid'], 'Nama' => $tenant['Nama']];
    }

    /**
     * @return array<string, mixed>
     */
    public static function Langganan(?StatusLangganan $status, bool $bolehBertransaksi): array
    {
        return ['Status' => $status?->value, 'BolehBertransaksi' => $bolehBertransaksi];
    }

    public static function Waktu(?Carbon $waktu): ?string
    {
        return $waktu?->copy()->utc()->toIso8601ZuluString();
    }
}
