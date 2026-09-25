<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Organisasi\Enum\PlatformPerangkat;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Tenant\Enum\AplikasiRilis;
use App\Domain\Tenant\Kueri\FlagFiturTenant;
use App\Domain\Tenant\Kueri\StatusLanggananTenant;
use App\Domain\Tenant\Kueri\VersiAplikasiPerangkat;
use App\Domain\Tenant\Model\RilisAplikasi;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use App\Http\Respons\Pos\V1\PerangkatPosRespons;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/pos/v1/konfigurasi-aplikasi` (§14.6, §16.3): versi terbaru & minimal per platform (P-10 `RilisAplikasi`
 * per perangkat: kanal & rollout bertahap; cadangan config/aplikasi.php), catatan rilis, flag fitur tenant (P-10,
 * kunci → hidup/mati), status langganan (boleh berjualan?), dan konfigurasi dasar outlet & perangkat. Tetap bisa
 * dibuka saat langganan ditangguhkan agar aplikasi bisa menampilkan alasannya.
 */
final class KonfigurasiAplikasiKontroler extends Kontroler
{
    public function Tampilkan(Request $permintaan, StatusLanggananTenant $statusLangganan, VersiAplikasiPerangkat $versi, FlagFiturTenant $flag): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $outlet = Outlet::query()->findOrFail($perangkat->IdOutlet);
        $status = $statusLangganan->Ambil($perangkat->IdTenant);
        $versiHeader = $permintaan->header('X-Versi-Aplikasi');
        $versiSaatIni = is_string($versiHeader) && $versiHeader !== '' ? $versiHeader : $perangkat->VersiAplikasi;

        $perPlatform = [];

        foreach (PlatformPerangkat::cases() as $platform) {
            $perPlatform[$platform->value] = $versi->Tentukan(AplikasiRilis::Pos, $platform->value, $perangkat->IdTenant, $perangkat->Uuid);
        }

        $versiPlatform = $perangkat->Platform === null ? null : $perPlatform[$perangkat->Platform->value];

        return response()->json([
            'Aplikasi' => [
                'Platform' => $perangkat->Platform?->value,
                'VersiSaatIni' => $versiSaatIni,
                'VersiTerbaru' => $versiPlatform['VersiTerbaru'] ?? null,
                'VersiMinimal' => $versiPlatform['VersiMinimal'] ?? null,
                'TautanUnduh' => $versiPlatform['TautanUnduh'] ?? null,
                'CatatanRilis' => $versiPlatform['CatatanRilis'] ?? null,
                'AdaPembaruan' => $versiPlatform !== null && $versiSaatIni !== null && RilisAplikasi::BandingkanVersi($versiSaatIni, $versiPlatform['VersiTerbaru']) < 0,
                'WajibPembaruan' => $versiPlatform !== null && $versiSaatIni !== null && RilisAplikasi::BandingkanVersi($versiSaatIni, $versiPlatform['VersiMinimal']) < 0,
                'PerPlatform' => array_map(fn (array $v): array => ['VersiTerbaru' => $v['VersiTerbaru'], 'VersiMinimal' => $v['VersiMinimal'], 'TautanUnduh' => $v['TautanUnduh']], $perPlatform),
            ],
            'FlagFitur' => (object) $flag->AmbilUntukTenant($perangkat->IdTenant),
            'Langganan' => PerangkatPosRespons::Langganan($status, $statusLangganan->CekBolehBertransaksiPos($status)),
            'Perangkat' => PerangkatPosRespons::Perangkat($perangkat),
            'Outlet' => PerangkatPosRespons::Outlet($outlet),
            'WaktuServer' => now()->utc()->toIso8601ZuluString(),
        ]);
    }
}
