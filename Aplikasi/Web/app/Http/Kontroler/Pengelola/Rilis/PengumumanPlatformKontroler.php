<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Rilis;

use App\Domain\Pengelola\Rilis\Aksi\KelolaPengumumanPlatform;
use App\Domain\Pengelola\Rilis\Data\DataPengumumanPlatform;
use App\Domain\Pengelola\Rilis\Kueri\DaftarPengumumanPlatform;
use App\Domain\Tenant\Enum\JenisPengumuman;
use App\Domain\Tenant\Enum\PlatformPengumuman;
use App\Domain\Tenant\Model\PengumumanPlatform;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pengumuman & banner pemeliharaan platform (P-10 PGL-19): lihat `rilis.lihat`; buat, ubah draf, terbitkan, cabut
 * `rilis.kelola`. Waktu dikirim peramban sebagai ISO UTC.
 */
final class PengumumanPlatformKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Daftar(DaftarPengumumanPlatform $kueri): Response
    {
        return Inertia::render('Pengelola/Rilis/Pengumuman', $kueri->Ambil());
    }

    public function Simpan(Request $permintaan, KelolaPengumumanPlatform $kelola): RedirectResponse
    {
        $p = $kelola->Simpan($this->AmbilPelaku(), $this->AmbilData($permintaan));

        return back()->with('Kilat', "Draf pengumuman \"{$p->Judul}\" disimpan.");
    }

    public function Ubah(PengumumanPlatform $pengumuman, Request $permintaan, KelolaPengumumanPlatform $kelola): RedirectResponse
    {
        $p = $kelola->Simpan($this->AmbilPelaku(), $this->AmbilData($permintaan), $pengumuman);

        return back()->with('Kilat', "Draf pengumuman \"{$p->Judul}\" diperbarui.");
    }

    public function Terbitkan(PengumumanPlatform $pengumuman, KelolaPengumumanPlatform $kelola): RedirectResponse
    {
        $p = $kelola->Terbitkan($this->AmbilPelaku(), $pengumuman);

        return back()->with('Kilat', "Pengumuman \"{$p->Judul}\" diterbitkan.");
    }

    public function Cabut(PengumumanPlatform $pengumuman, Request $permintaan, KelolaPengumumanPlatform $kelola): RedirectResponse
    {
        $permintaan->validate(['Alasan' => ['required', 'string', 'max:255']], ['Alasan.*' => 'Tulis alasan mencabut pengumuman.']);
        $p = $kelola->Cabut($this->AmbilPelaku(), $pengumuman, $permintaan->string('Alasan')->toString());

        return back()->with('Kilat', "Pengumuman \"{$p->Judul}\" dicabut.");
    }

    private function AmbilData(Request $permintaan): DataPengumumanPlatform
    {
        $valid = $permintaan->validate([
            'Judul' => ['required', 'string', 'max:120'],
            'Isi' => ['required', 'string', 'max:1000'],
            'Jenis' => ['required', Rule::enum(JenisPengumuman::class)],
            'Tautan' => ['nullable', 'url:https', 'max:255'],
            'TampilMulai' => ['required', 'date'],
            'TampilSampai' => ['required', 'date'],
            'PemeliharaanMulai' => ['nullable', 'date'],
            'PemeliharaanSelesai' => ['nullable', 'date'],
            'Sasaran' => ['nullable', 'array'],
            'Sasaran.KodePaket' => ['nullable', 'array', 'max:50'],
            'Sasaran.KodePaket.*' => ['string', 'max:30'],
            'Sasaran.Sektor' => ['nullable', 'array', 'max:50'],
            'Sasaran.Sektor.*' => ['string', 'max:30'],
            'Sasaran.Platform' => ['nullable', 'array', 'max:4'],
            'Sasaran.Platform.*' => ['string', Rule::enum(PlatformPengumuman::class)],
            'Sasaran.VersiMinimal' => ['nullable', 'string', 'max:20'],
            'Sasaran.VersiMaksimal' => ['nullable', 'string', 'max:20'],
        ], attributes: [
            'Judul' => 'judul', 'Isi' => 'isi', 'Jenis' => 'jenis', 'Tautan' => 'tautan',
            'TampilMulai' => 'awal tampil', 'TampilSampai' => 'akhir tampil',
            'PemeliharaanMulai' => 'mulai pemeliharaan', 'PemeliharaanSelesai' => 'selesai pemeliharaan',
        ]);
        $sasaran = is_array($valid['Sasaran'] ?? null) ? $valid['Sasaran'] : [];
        $daftar = static fn (string $kunci): array => array_values(array_unique(array_filter(
            array_map(fn (mixed $v): string => is_string($v) ? trim($v) : '', is_array($sasaran[$kunci] ?? null) ? $sasaran[$kunci] : []),
            fn (string $v): bool => $v !== '',
        )));
        $versi = static fn (string $kunci): ?string => is_string($sasaran[$kunci] ?? null) && trim($sasaran[$kunci]) !== '' ? trim($sasaran[$kunci]) : null;
        $waktu = static fn (mixed $nilai): ?CarbonImmutable => is_string($nilai) && $nilai !== '' ? CarbonImmutable::parse($nilai)->utc() : null;

        return new DataPengumumanPlatform(
            (string) $valid['Judul'],
            (string) $valid['Isi'],
            JenisPengumuman::from((string) $valid['Jenis']),
            [
                'KodePaket' => $daftar('KodePaket'),
                'Sektor' => $daftar('Sektor'),
                'Platform' => $daftar('Platform'),
                'VersiMinimal' => $versi('VersiMinimal'),
                'VersiMaksimal' => $versi('VersiMaksimal'),
            ],
            isset($valid['Tautan']) && $valid['Tautan'] !== '' ? (string) $valid['Tautan'] : null,
            $waktu($valid['TampilMulai']) ?? CarbonImmutable::now(),
            $waktu($valid['TampilSampai']) ?? CarbonImmutable::now(),
            $waktu($valid['PemeliharaanMulai'] ?? null),
            $waktu($valid['PemeliharaanSelesai'] ?? null),
        );
    }
}
