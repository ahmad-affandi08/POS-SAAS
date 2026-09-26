<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Rilis\Aksi;

use App\Domain\Organisasi\Model\Perangkat;
use App\Domain\Pengelola\Tenant\Layanan\KonteksPengelola;
use App\Domain\Tenant\Enum\JenisKompatibilitas;
use App\Domain\Tenant\Enum\StatusKompatibilitas;
use App\Domain\Tenant\Model\KompatibilitasPerangkat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Hardware Compatibility List otomatis (PRD §17.2.5a, v1.98): agregat `Perangkat.ProfilHardware` (hasil Wizard Uji
 * Perangkat) lintas tenant, dibaca lewat `KonteksPengelola` (diaudit `tenant.data.akses`). Hanya merek/model & nama
 * printer yang disimpan; nama tenant tidak.
 *
 * - **Perangkat** per produsen + model: lolos bila tidak ada langkah uji yang gagal dan minimal satu berhasil.
 * - **Printer** per jenis sambungan + nama (printer LAN/Wi-Fi dan printer sistem dilewati karena namanya hanya alamat):
 *   lolos bila cetak berhasil dan potong kertas tidak gagal; gagal bila cetak atau potong gagal (laci tidak dinilai
 *   karena bergantung kabel laci).
 * - `StatusOtomatis`: belum ada hasil = `BelumDiuji`; gagal ≥ lolos = `Terbatas`; selain itu `Kompatibel`. Baris yang
 *   tidak lagi dilaporkan angka-angkanya menjadi 0. `StatusManual` & catatan tim tidak disentuh.
 *
 * Dijalankan terjadwal harian (`pengelola:segarkan-kompatibilitas`) dan dari halaman pengelola.
 */
final class SegarkanKompatibilitasPerangkat
{
    /** Sambungan printer yang namanya bukan model printer. */
    private const SAMBUNGAN_TANPA_MODEL = ['Jaringan', 'CetakSistem'];

    public function __construct(private readonly KonteksPengelola $konteks) {}

    /**
     * @return array{Perangkat: int, Printer: int}
     */
    public function Jalankan(): array
    {
        $kelompok = $this->konteks->JalankanLintasTenant(
            'HCL: menyegarkan daftar kompatibilitas perangkat dari profil hardware',
            fn (KonteksPengelola $k): array => $this->Kelompokkan(
                $k->KueriLintas(Perangkat::class)
                    ->whereNull('DicabutPada')
                    ->whereNotNull('ProfilHardware')
                    ->get(['Id', 'IdTenant', 'ProfilHardware']),
            ),
        );

        $sekarang = now();

        return DB::transaction(function () use ($kelompok, $sekarang): array {
            $terlihat = [];

            foreach ($kelompok as $g) {
                $baris = KompatibilitasPerangkat::query()
                    ->where('Jenis', $g['Jenis']->value)
                    ->where('Kunci', $g['Kunci'])
                    ->lockForUpdate()
                    ->first() ?? new KompatibilitasPerangkat(['Jenis' => $g['Jenis'], 'Kunci' => $g['Kunci']]);
                $baris->fill([
                    'Nama' => $g['Nama'],
                    'Sambungan' => $g['Sambungan'],
                    'JumlahPerangkat' => $g['Perangkat'],
                    'JumlahTenant' => count($g['Tenant']),
                    'JumlahLolos' => $g['Lolos'],
                    'JumlahGagal' => $g['Gagal'],
                    'StatusOtomatis' => self::TentukanStatus($g['Lolos'], $g['Gagal']),
                    'TerakhirDiujiPada' => $g['TerakhirDiuji'],
                    'DisegarkanPada' => $sekarang,
                ])->save();
                $terlihat[] = $baris->Id;
            }

            KompatibilitasPerangkat::query()->whereNotIn('Id', $terlihat)->update([
                'JumlahPerangkat' => 0,
                'JumlahTenant' => 0,
                'JumlahLolos' => 0,
                'JumlahGagal' => 0,
                'StatusOtomatis' => StatusKompatibilitas::BelumDiuji->value,
                'DisegarkanPada' => $sekarang,
            ]);

            $jumlah = array_count_values(array_map(fn (array $g): string => $g['Jenis']->value, $kelompok));

            return ['Perangkat' => $jumlah['Perangkat'] ?? 0, 'Printer' => $jumlah['Printer'] ?? 0];
        });
    }

    public static function TentukanStatus(int $lolos, int $gagal): StatusKompatibilitas
    {
        if ($lolos + $gagal === 0) {
            return StatusKompatibilitas::BelumDiuji;
        }

        return $gagal >= $lolos && $gagal > 0 ? StatusKompatibilitas::Terbatas : StatusKompatibilitas::Kompatibel;
    }

    /**
     * @param  iterable<Perangkat>  $perangkat
     * @return list<array{Jenis: JenisKompatibilitas, Kunci: string, Nama: string, Sambungan: string|null, Perangkat: int, Tenant: array<int, true>, Lolos: int, Gagal: int, TerakhirDiuji: CarbonImmutable|null}>
     */
    private function Kelompokkan(iterable $perangkat): array
    {
        $hasil = [];
        $teks = fn (mixed $nilai): string => is_string($nilai) ? trim((string) preg_replace('/\s+/', ' ', $nilai)) : '';

        foreach ($perangkat as $p) {
            $profil = $p->ProfilHardware ?? [];
            $uji = is_array($profil['Uji'] ?? null) ? $profil['Uji'] : [];
            $diuji = $this->UraiWaktu($profil['DiujiPada'] ?? null);

            $nama = trim($teks($profil['Produsen'] ?? null).' '.$teks($profil['Model'] ?? null));

            if ($nama !== '') {
                $gagal = in_array('Gagal', $uji, true);
                $lolos = ! $gagal && in_array('Lolos', $uji, true);
                $this->Tambah($hasil, JenisKompatibilitas::Perangkat, $nama, null, $p->IdTenant, $lolos, $gagal, $diuji);
            }

            $printer = is_array($profil['Printer'] ?? null) ? $profil['Printer'] : [];
            $sambungan = $teks($printer['Jenis'] ?? null);
            $namaPrinter = $teks($printer['Nama'] ?? null);

            if ($namaPrinter !== '' && ! in_array($sambungan, self::SAMBUNGAN_TANPA_MODEL, true)) {
                $gagal = ($uji['Cetak'] ?? null) === 'Gagal' || ($uji['Potong'] ?? null) === 'Gagal';
                $lolos = ! $gagal && ($uji['Cetak'] ?? null) === 'Lolos';
                $this->Tambah($hasil, JenisKompatibilitas::Printer, $namaPrinter, $sambungan === '' ? null : $sambungan, $p->IdTenant, $lolos, $gagal, $diuji);
            }
        }

        return array_values($hasil);
    }

    /**
     * @param  array<string, array{Jenis: JenisKompatibilitas, Kunci: string, Nama: string, Sambungan: string|null, Perangkat: int, Tenant: array<int, true>, Lolos: int, Gagal: int, TerakhirDiuji: CarbonImmutable|null}>  $hasil
     */
    private function Tambah(array &$hasil, JenisKompatibilitas $jenis, string $nama, ?string $sambungan, int $idTenant, bool $lolos, bool $gagal, ?CarbonImmutable $diuji): void
    {
        $kunci = mb_substr(mb_strtolower(($sambungan === null ? '' : $sambungan.'|').$nama), 0, 190);
        $indeks = $jenis->value.'#'.$kunci;
        $hasil[$indeks] ??= [
            'Jenis' => $jenis,
            'Kunci' => $kunci,
            'Nama' => mb_substr($nama, 0, 190),
            'Sambungan' => $sambungan,
            'Perangkat' => 0,
            'Tenant' => [],
            'Lolos' => 0,
            'Gagal' => 0,
            'TerakhirDiuji' => null,
        ];
        $hasil[$indeks]['Perangkat']++;
        $hasil[$indeks]['Tenant'][$idTenant] = true;
        $hasil[$indeks]['Lolos'] += $lolos ? 1 : 0;
        $hasil[$indeks]['Gagal'] += $gagal ? 1 : 0;

        if ($diuji !== null && ($hasil[$indeks]['TerakhirDiuji'] === null || $diuji->greaterThan($hasil[$indeks]['TerakhirDiuji']))) {
            $hasil[$indeks]['TerakhirDiuji'] = $diuji;
        }
    }

    private function UraiWaktu(mixed $nilai): ?CarbonImmutable
    {
        if (! is_string($nilai) || $nilai === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($nilai)->utc();
        } catch (Throwable) {
            return null;
        }
    }
}
