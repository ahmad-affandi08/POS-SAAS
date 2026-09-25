<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Kueri;

use App\Domain\Akuntansi\Layanan\PenjagaKunciPeriode;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Karyawan\Enum\CakupanTargetPenjualan;
use App\Domain\Karyawan\Model\Karyawan;
use App\Domain\Karyawan\Model\TargetPenjualan;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Penjualan\Data\DataSaringLaporanPenjualan;
use App\Domain\Penjualan\Kueri\AgregatPenjualan;
use App\Domain\Tenant\Kueri\ProfilTenant;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * Progres target penjualan satu periode (F-18 bagian 3, EMP-05):
 * - Outlet: realisasi = penjualan bersih (kotor − diskon − retur, tanpa void) tanggal bisnis periode, seperti laporan
 *   penjualan.
 * - Karyawan: realisasi = Σ dasar baris yang ia layani × porsinya, dikurangi void/retur (`LaporanKomisi`).
 * Persen = realisasi ÷ target (1 desimal). Proyeksi (bulan berjalan saja) = realisasi ÷ hari berlalu × jumlah hari.
 */
final class ProgresTargetPenjualan
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly ProfilTenant $profil,
        private readonly PetaUuidOutlet $outlet,
        private readonly AgregatPenjualan $agregat,
        private readonly LaporanKomisi $komisi,
    ) {}

    /**
     * @param  list<int>|null  $idOutletBoleh  null = semua outlet
     * @return list<array<string, mixed>>
     */
    public function Ambil(string $periode, ?array $idOutletBoleh): array
    {
        $awal = CarbonImmutable::createFromFormat('!Y-m', $periode) ?: CarbonImmutable::today()->startOfMonth();
        $akhir = $awal->endOfMonth()->startOfDay();
        $target = TargetPenjualan::query()
            ->where('Periode', $periode)
            ->when($idOutletBoleh !== null, fn ($k) => $k->where(fn ($k) => $k->whereNull('IdOutlet')->orWhereIn('IdOutlet', $idOutletBoleh)))
            ->get();

        $idOutlet = array_values(array_unique(array_filter($target->pluck('IdOutlet')->all(), fn ($id): bool => $id !== null)));
        $outlet = [];

        foreach ($this->outlet->AmbilRingkas($idOutlet === [] ? [0] : array_map('intval', $idOutlet)) as $o) {
            $outlet[$o['Id']] = $o;
        }

        $realisasiOutlet = [];

        if ($idOutlet !== []) {
            foreach ($this->agregat->Agregasi(new DataSaringLaporanPenjualan($awal, $akhir, array_map('intval', $idOutlet)), ['Outlet']) as $kunci => $b) {
                $realisasiOutlet[(int) $kunci] = $b['Agregat']->Bersih();
            }
        }

        $realisasiKaryawan = $this->komisi->AmbilPenjualanPerKaryawan($awal->toDateString(), $akhir->toDateString());
        $karyawan = Karyawan::query()->whereKey($target->pluck('IdKaryawan')->filter()->all())->get(['Id', 'Uuid', 'Nama'])->keyBy('Id');
        [$hariBerlalu, $jumlahHari] = $this->HitungHari($awal);

        $hasil = [];

        foreach ($target as $t) {
            $realisasi = $t->Cakupan === CakupanTargetPenjualan::Outlet
                ? ($realisasiOutlet[$t->IdOutlet] ?? Uang::Nol())
                : Uang::Dari($realisasiKaryawan[$t->IdKaryawan] ?? '0');
            $nilai = Uang::Dari($t->Nilai);
            $sisa = $nilai->Kurangi($realisasi);
            $proyeksi = $hariBerlalu === null ? null : Uang::Dari(BigDecimal::of($realisasi->KeString())->multipliedBy($jumlahHari)->dividedBy($hariBerlalu, 2, RoundingMode::HalfUp));
            $sasaran = $t->Cakupan === CakupanTargetPenjualan::Outlet ? ($outlet[$t->IdOutlet] ?? null) : $karyawan->get($t->IdKaryawan)?->only(['Uuid', 'Nama']);

            $hasil[] = [
                'Uuid' => $t->Uuid,
                'Cakupan' => $t->Cakupan->value,
                'LabelCakupan' => $t->Cakupan->AmbilLabel(),
                'UuidSasaran' => $sasaran['Uuid'] ?? '',
                'NamaSasaran' => $sasaran['Nama'] ?? '',
                'Nilai' => $nilai->KeString(),
                'Realisasi' => $realisasi->KeString(),
                'Persen' => (string) BigDecimal::of($realisasi->KeString())->multipliedBy(100)->dividedBy($t->Nilai, 1, RoundingMode::Down),
                'Sisa' => ($sisa->BernilaiNegatif() ? Uang::Nol() : $sisa)->KeString(),
                'Proyeksi' => $proyeksi?->KeString(),
            ];
        }

        usort($hasil, fn (array $a, array $b): int => [$a['Cakupan'] === 'Outlet' ? 0 : 1, $a['NamaSasaran']] <=> [$b['Cakupan'] === 'Outlet' ? 0 : 1, $b['NamaSasaran']]);

        return $hasil;
    }

    /**
     * Pilihan periode: 2 bulan ke depan sampai 11 bulan ke belakang dari bulan berjalan (zona waktu tenant).
     *
     * @return array{Berjalan: string, Opsi: list<array{Nilai: string, Label: string}>}
     */
    public function AmbilOpsiPeriode(): array
    {
        $bulan = $this->HariIni()->startOfMonth();
        $opsi = [];

        for ($i = -2; $i < 12; $i++) {
            $periode = $bulan->subMonthsNoOverflow($i)->format('Y-m');
            $opsi[] = ['Nilai' => $periode, 'Label' => PenjagaKunciPeriode::FormatPeriode($periode)];
        }

        return ['Berjalan' => $bulan->format('Y-m'), 'Opsi' => $opsi];
    }

    /**
     * @return array{0: int|null, 1: int} hari berlalu (null bila bukan bulan berjalan) dan jumlah hari periode
     */
    private function HitungHari(CarbonImmutable $awal): array
    {
        $hariIni = $this->HariIni();

        return [$hariIni->format('Y-m') === $awal->format('Y-m') ? $hariIni->day : null, $awal->daysInMonth];
    }

    private function HariIni(): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::now($this->profil->Ambil($this->konteks->Wajib())['ZonaWaktu'])->toDateString());
    }
}
