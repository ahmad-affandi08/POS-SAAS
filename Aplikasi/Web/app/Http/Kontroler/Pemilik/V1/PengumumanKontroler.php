<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pemilik\V1;

use App\Domain\Organisasi\Kueri\SektorOutletTenant;
use App\Domain\Tenant\Enum\PlatformPengumuman;
use App\Domain\Tenant\Kueri\PengumumanBerlaku;
use App\Domain\Tenant\Kueri\RingkasanLanggananTenant;
use Illuminate\Http\JsonResponse;

/**
 * `GET /api/pemilik/v1/pengumuman` (P-10 PGL-19, v3.47): pengumuman platform yang berlaku untuk tenant aktif di
 * Aplikasi Pemilik — sasaran paket & sektor tenant, platform `Pemilik` (sasaran platform kosong = semua). Rentang
 * versi tidak dinilai: versi di sasaran adalah versi aplikasi kasir. Tanpa izin khusus: setiap pengguna Pemilik perlu
 * tahu jadwal pemeliharaan.
 */
final class PengumumanKontroler extends DasarPemilikKontroler
{
    public function Daftar(PengumumanBerlaku $pengumuman, RingkasanLanggananTenant $langganan, SektorOutletTenant $sektor): JsonResponse
    {
        $kodePaket = $langganan->Ambil($this->IdTenant())['KodePaket'] ?? null;

        return response()->json([
            'Pengumuman' => $pengumuman->AmbilUntuk(PlatformPengumuman::Pemilik, $kodePaket, $sektor->AmbilKode(), null),
        ]);
    }
}
