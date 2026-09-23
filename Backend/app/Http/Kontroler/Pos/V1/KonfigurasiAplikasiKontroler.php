<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Organisasi\Enum\PlatformPerangkat;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Tenant\Kueri\StatusLanggananTenant;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use App\Http\Respons\Pos\V1\PerangkatPosRespons;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET /api/pos/v1/konfigurasi-aplikasi` (§14.6, §16.3): versi terbaru & minimal per platform (config/aplikasi.php),
 * status langganan (boleh berjualan?), dan konfigurasi dasar outlet & perangkat. Tetap bisa dibuka saat langganan
 * ditangguhkan agar aplikasi bisa menampilkan alasannya.
 *
 * TODO P-10: flag fitur remote & kanal rilis dari `RilisAplikasi`.
 */
final class KonfigurasiAplikasiKontroler extends Kontroler
{
    public function Tampilkan(Request $permintaan, StatusLanggananTenant $statusLangganan): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $outlet = Outlet::query()->findOrFail($perangkat->IdOutlet);
        $status = $statusLangganan->Ambil($perangkat->IdTenant);
        $versiHeader = $permintaan->header('X-Versi-Aplikasi');
        $versiSaatIni = is_string($versiHeader) && $versiHeader !== '' ? $versiHeader : $perangkat->VersiAplikasi;

        $perPlatform = [];

        foreach (PlatformPerangkat::cases() as $platform) {
            $perPlatform[$platform->value] = self::AmbilVersi($platform);
        }

        $versiPlatform = $perangkat->Platform === null ? null : $perPlatform[$perangkat->Platform->value];

        return response()->json([
            'Aplikasi' => [
                'Platform' => $perangkat->Platform?->value,
                'VersiSaatIni' => $versiSaatIni,
                'VersiTerbaru' => $versiPlatform['VersiTerbaru'] ?? null,
                'VersiMinimal' => $versiPlatform['VersiMinimal'] ?? null,
                'TautanUnduh' => $versiPlatform['TautanUnduh'] ?? null,
                'AdaPembaruan' => $versiPlatform !== null && $versiSaatIni !== null && self::BandingkanVersi($versiSaatIni, $versiPlatform['VersiTerbaru']) < 0,
                'WajibPembaruan' => $versiPlatform !== null && $versiSaatIni !== null && self::BandingkanVersi($versiSaatIni, $versiPlatform['VersiMinimal']) < 0,
                'PerPlatform' => $perPlatform,
            ],
            'FlagFitur' => new \stdClass,
            'Langganan' => PerangkatPosRespons::Langganan($status, $statusLangganan->CekBolehBertransaksiPos($status)),
            'Perangkat' => PerangkatPosRespons::Perangkat($perangkat),
            'Outlet' => PerangkatPosRespons::Outlet($outlet),
            'WaktuServer' => now()->utc()->toIso8601ZuluString(),
        ]);
    }

    /**
     * @return array{VersiTerbaru: string, VersiMinimal: string, TautanUnduh: string|null}
     */
    private static function AmbilVersi(PlatformPerangkat $platform): array
    {
        $konfigurasi = config("aplikasi.Pos.{$platform->value}");
        $konfigurasi = is_array($konfigurasi) ? $konfigurasi : [];
        $tautan = $konfigurasi['TautanUnduh'] ?? null;

        return [
            'VersiTerbaru' => (string) ($konfigurasi['VersiTerbaru'] ?? '1.0.0'),
            'VersiMinimal' => (string) ($konfigurasi['VersiMinimal'] ?? '1.0.0'),
            'TautanUnduh' => is_string($tautan) && $tautan !== '' ? $tautan : null,
        ];
    }

    /** Versi semantik tanpa bagian `+BUILD` (§14.6). */
    private static function BandingkanVersi(string $a, string $b): int
    {
        return version_compare(explode('+', $a)[0], explode('+', $b)[0]);
    }
}
