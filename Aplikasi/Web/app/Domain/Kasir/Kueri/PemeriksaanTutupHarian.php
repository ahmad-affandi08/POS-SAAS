<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Kueri;

use App\Domain\Kasir\Model\TutupHarian;
use App\Domain\Organisasi\Kueri\DaftarAnggota;
use App\Domain\Organisasi\Kueri\PerangkatBelumSinkron;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Organisasi\Model\Perangkat;
use App\Domain\Penjualan\Kueri\AgregatPenjualan;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Syarat & peringatan tutup harian F-15 per outlet per tanggal bisnis, dan daftar 14 hari terakhir tiap outlet untuk
 * halaman Tutup harian.
 * - Wajib: tanggal sudah berjalan (tidak setelah tanggal bisnis outlet saat ini) dan semua shift tanggal itu ditutup.
 * - Peringatan (boleh diabaikan dengan konfirmasi): perangkat kasir/pelayan yang belum menghubungi server sejak hari
 *   berakhir (hari berjalan: dalam 30 menit terakhir), dan penjualan yang ditandai `PerluTinjauan`.
 */
final class PemeriksaanTutupHarian
{
    public const JUMLAH_HARI = 14;

    /** Hari berjalan: perangkat dianggap sudah sinkron bila menghubungi server dalam rentang ini. */
    public const MENIT_SINKRON_HARI_BERJALAN = 30;

    public function __construct(
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly ShiftBelumDitutup $shift,
        private readonly PerangkatBelumSinkron $perangkat,
        private readonly AgregatPenjualan $agregat,
        private readonly PetaUuidOutlet $outlet,
        private readonly DaftarAnggota $anggota,
    ) {}

    /**
     * @return array{BelumBerjalan: bool, Berjalan: bool, ShiftBelumDitutup: int, Peringatan: list<array{Kode: string, Pesan: string}>}
     */
    public function Periksa(int $idOutlet, CarbonImmutable $tanggal): array
    {
        $tanggal = $tanggal->startOfDay();
        $hariIni = $this->tanggalBisnis->Hitung($idOutlet);
        $kunci = $idOutlet.'|'.$tanggal->toDateString();

        return $this->Susun(
            $idOutlet,
            $tanggal,
            $hariIni,
            $this->shift->HitungPerHari([$idOutlet], $tanggal, $tanggal)[$kunci] ?? 0,
            $this->agregat->HitungPerluTinjauanPerHari([$idOutlet], $tanggal, $tanggal)[$kunci] ?? 0,
            $this->perangkat->AmbilPerOutlet([$idOutlet])[$idOutlet] ?? [],
        );
    }

    /**
     * Outlet aktif yang boleh diakses × 14 tanggal bisnis terakhir (terbaru dulu).
     *
     * @param  list<int>|null  $idOutletBoleh  null = semua outlet
     * @return list<array{Kunci: string, Outlet: string, NamaOutlet: string, TanggalBisnis: string, Berjalan: bool, Ditutup: bool, DitutupPada: string|null, DitutupOleh: string|null, JumlahTransaksi: int|null, PenjualanBersih: string|null, ShiftBelumDitutup: int, Peringatan: list<array{Kode: string, Pesan: string}>}>
     */
    public function Daftar(int $idTenant, ?array $idOutletBoleh): array
    {
        $outlet = $this->outlet->AmbilRingkas($idOutletBoleh, hanyaAktif: true);

        if ($outlet === []) {
            return [];
        }

        $idOutlet = array_column($outlet, 'Id');
        $hariIni = [];

        foreach ($idOutlet as $id) {
            $hariIni[$id] = $this->tanggalBisnis->Hitung($id);
        }

        $sampai = max($hariIni);
        $dari = min($hariIni)->subDays(self::JUMLAH_HARI - 1);
        $shift = $this->shift->HitungPerHari($idOutlet, $dari, $sampai);
        $tinjauan = $this->agregat->HitungPerluTinjauanPerHari($idOutlet, $dari, $sampai);
        $perangkat = $this->perangkat->AmbilPerOutlet($idOutlet);
        $tutup = [];

        foreach (TutupHarian::query()->whereIn('IdOutlet', $idOutlet)->whereBetween('TanggalBisnis', [$dari->toDateString(), $sampai->toDateString()])->get() as $baris) {
            $tutup[$baris->IdOutlet.'|'.$baris->TanggalBisnis->toDateString()] = $baris;
        }

        $nama = $this->anggota->AmbilNamaPengguna($idTenant, array_values(array_unique(array_map(fn (TutupHarian $t): int => $t->DitutupOleh, $tutup))));
        $hasil = [];

        for ($i = 0; $i < self::JUMLAH_HARI; $i++) {
            foreach ($outlet as $o) {
                $tanggal = $hariIni[$o['Id']]->subDays($i);
                $kunci = $o['Id'].'|'.$tanggal->toDateString();
                $baris = $tutup[$kunci] ?? null;
                $periksa = $baris === null
                    ? $this->Susun($o['Id'], $tanggal, $hariIni[$o['Id']], $shift[$kunci] ?? 0, $tinjauan[$kunci] ?? 0, $perangkat[$o['Id']] ?? [])
                    : ['Berjalan' => $i === 0, 'ShiftBelumDitutup' => 0, 'Peringatan' => $baris->Peringatan ?? []];

                $hasil[] = [
                    'Kunci' => $o['Uuid'].'_'.$tanggal->toDateString(),
                    'Outlet' => $o['Uuid'],
                    'NamaOutlet' => $o['Nama'],
                    'TanggalBisnis' => $tanggal->toDateString(),
                    'Berjalan' => $periksa['Berjalan'],
                    'Ditutup' => $baris !== null,
                    'DitutupPada' => $baris?->DitutupPada->toIso8601String(),
                    'DitutupOleh' => $baris === null ? null : ($nama[$baris->DitutupOleh] ?? null),
                    'JumlahTransaksi' => $baris?->JumlahTransaksi,
                    'PenjualanBersih' => $baris?->PenjualanBersih,
                    'ShiftBelumDitutup' => $periksa['ShiftBelumDitutup'],
                    'Peringatan' => $periksa['Peringatan'],
                ];
            }
        }

        return $hasil;
    }

    /**
     * @param  list<Perangkat>  $perangkat
     * @return array{BelumBerjalan: bool, Berjalan: bool, ShiftBelumDitutup: int, Peringatan: list<array{Kode: string, Pesan: string}>}
     */
    private function Susun(int $idOutlet, CarbonImmutable $tanggal, CarbonImmutable $hariIni, int $shift, int $tinjauan, array $perangkat): array
    {
        // Bandingkan tanggal kalender (hari ini = awal hari di zona outlet, `$tanggal` bisa berzona lain).
        $berjalan = $tanggal->toDateString() === $hariIni->toDateString();
        $belumBerjalan = $tanggal->toDateString() > $hariIni->toDateString();
        $peringatan = [];

        if (! $belumBerjalan) {
            $batas = $this->HitungBatasSinkron($idOutlet, $tanggal, $berjalan);
            $belumSinkron = PerangkatBelumSinkron::Saring($perangkat, $batas);

            if ($belumSinkron !== []) {
                $peringatan[] = [
                    'Kode' => 'PerangkatBelumSinkron',
                    'Pesan' => count($belumSinkron).' perangkat belum tersambung ke server sejak hari berakhir ('.implode(', ', array_column($belumSinkron, 'Nama')).'). Transaksi offline-nya mungkin belum masuk.',
                ];
            }

            if ($tinjauan > 0) {
                $peringatan[] = ['Kode' => 'PenjualanPerluTinjauan', 'Pesan' => "{$tinjauan} penjualan ditandai perlu ditinjau."];
            }
        }

        return ['BelumBerjalan' => $belumBerjalan, 'Berjalan' => $berjalan, 'ShiftBelumDitutup' => $shift, 'Peringatan' => $peringatan];
    }

    private function HitungBatasSinkron(int $idOutlet, CarbonInterface $tanggal, bool $berjalan): CarbonImmutable
    {
        $akhirHari = $this->tanggalBisnis->AmbilAkhirHari($idOutlet, $tanggal);
        $baruSaja = CarbonImmutable::now()->subMinutes(self::MENIT_SINKRON_HARI_BERJALAN);

        return $berjalan || $akhirHari->gt($baruSaja) ? $baruSaja : $akhirHari;
    }
}
