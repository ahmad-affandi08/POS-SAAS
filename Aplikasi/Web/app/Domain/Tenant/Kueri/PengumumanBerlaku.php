<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Kueri;

use App\Domain\Tenant\Enum\JenisPengumuman;
use App\Domain\Tenant\Enum\PlatformPengumuman;
use App\Domain\Tenant\Enum\StatusPengumuman;
use App\Domain\Tenant\Model\PengumumanPlatform;
use App\Domain\Tenant\Model\RilisAplikasi;
use Illuminate\Support\Facades\Cache;

/**
 * Pengumuman platform yang sedang berlaku untuk satu tenant/perangkat (P-10 PGL-19): status `Terbit`, sekarang di
 * dalam masa tampil, dan cocok dengan sasaran (kosong = semua): paket langganan, salah satu sektor outlet, platform,
 * dan rentang versi aplikasi (hanya dinilai bila versinya diketahui; back-office web tidak berversi). Urut: Penting,
 * Pemeliharaan, Yang baru, Info; terbaru dulu; paling banyak `MAKS`. Daftar terbit ditembolok `DETIK_TEMBOLOK` detik
 * dan dilupakan saat terbit/cabut.
 */
final class PengumumanBerlaku
{
    public const MAKS = 5;

    public const DETIK_TEMBOLOK = 60;

    private const KUNCI_TEMBOLOK = 'pengumuman-platform:terbit';

    /**
     * @param  list<string>  $sektor
     * @return list<array{Uuid: string, Judul: string, Isi: string, Jenis: string, LabelJenis: string, Tautan: string|null, BolehDitutup: bool, PemeliharaanMulai: string|null, PemeliharaanSelesai: string|null, TampilSampai: string}>
     */
    public function AmbilUntuk(PlatformPengumuman $platform, ?string $kodePaket, array $sektor, ?string $versi): array
    {
        $sekarang = now();
        $urutan = [JenisPengumuman::Penting->value => 0, JenisPengumuman::Pemeliharaan->value => 1, JenisPengumuman::YangBaru->value => 2, JenisPengumuman::Info->value => 3];
        $cocok = array_values(array_filter(self::AmbilTerbit(), function (array $p) use ($platform, $kodePaket, $sektor, $versi, $sekarang): bool {
            $s = $p['Sasaran'];

            return $p['TampilMulai'] <= $sekarang->getTimestamp() && $p['TampilSampai'] > $sekarang->getTimestamp()
                && (($s['Platform'] ?? []) === [] || in_array($platform->value, $s['Platform'] ?? [], true))
                && (($s['KodePaket'] ?? []) === [] || ($kodePaket !== null && in_array($kodePaket, $s['KodePaket'] ?? [], true)))
                && (($s['Sektor'] ?? []) === [] || array_intersect($sektor, $s['Sektor'] ?? []) !== [])
                && ($versi === null || ($s['VersiMinimal'] ?? null) === null || RilisAplikasi::BandingkanVersi($versi, (string) $s['VersiMinimal']) >= 0)
                && ($versi === null || ($s['VersiMaksimal'] ?? null) === null || RilisAplikasi::BandingkanVersi($versi, (string) $s['VersiMaksimal']) <= 0);
        }));

        usort($cocok, fn (array $a, array $b): int => $urutan[$a['Jenis']] <=> $urutan[$b['Jenis']] ?: $b['TampilMulai'] <=> $a['TampilMulai']);

        return array_map(fn (array $p): array => [
            'Uuid' => $p['Uuid'],
            'Judul' => $p['Judul'],
            'Isi' => $p['Isi'],
            'Jenis' => $p['Jenis'],
            'LabelJenis' => JenisPengumuman::from($p['Jenis'])->AmbilLabel(),
            'Tautan' => $p['Tautan'],
            'BolehDitutup' => JenisPengumuman::from($p['Jenis'])->CekBolehDitutup(),
            'PemeliharaanMulai' => $p['PemeliharaanMulai'],
            'PemeliharaanSelesai' => $p['PemeliharaanSelesai'],
            'TampilSampai' => gmdate('Y-m-d\TH:i:s\Z', $p['TampilSampai']),
        ], array_slice($cocok, 0, self::MAKS));
    }

    public static function LupakanTembolok(): void
    {
        Cache::forget(self::KUNCI_TEMBOLOK);
    }

    /**
     * Semua pengumuman terbit yang belum lewat masa tampilnya (bentuk sederhana yang aman ditembolok).
     *
     * @return list<array{Uuid: string, Judul: string, Isi: string, Jenis: string, Sasaran: array<string, mixed>, Tautan: string|null, TampilMulai: int, TampilSampai: int, PemeliharaanMulai: string|null, PemeliharaanSelesai: string|null}>
     */
    private static function AmbilTerbit(): array
    {
        /** @var list<array{Uuid: string, Judul: string, Isi: string, Jenis: string, Sasaran: array<string, mixed>, Tautan: string|null, TampilMulai: int, TampilSampai: int, PemeliharaanMulai: string|null, PemeliharaanSelesai: string|null}> */
        return Cache::remember(self::KUNCI_TEMBOLOK, self::DETIK_TEMBOLOK, fn (): array => array_values(PengumumanPlatform::query()
            ->where('Status', StatusPengumuman::Terbit->value)
            ->where('TampilSampai', '>', now())
            ->orderBy('TampilMulai')
            ->limit(100)
            ->get()
            ->map(fn (PengumumanPlatform $p): array => [
                'Uuid' => $p->Uuid,
                'Judul' => $p->Judul,
                'Isi' => $p->Isi,
                'Jenis' => $p->Jenis->value,
                'Sasaran' => $p->Sasaran,
                'Tautan' => $p->Tautan,
                'TampilMulai' => $p->TampilMulai->getTimestamp(),
                'TampilSampai' => $p->TampilSampai->getTimestamp(),
                'PemeliharaanMulai' => $p->PemeliharaanMulai?->toIso8601ZuluString(),
                'PemeliharaanSelesai' => $p->PemeliharaanSelesai?->toIso8601ZuluString(),
            ])
            ->all()));
    }
}
