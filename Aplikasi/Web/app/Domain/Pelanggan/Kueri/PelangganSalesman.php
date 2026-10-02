<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Pelanggan\Enum\StatusPelanggan;
use App\Domain\Pelanggan\Enum\StatusPiutang;
use App\Domain\Pelanggan\Layanan\NomorHp;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\Piutang;
use Carbon\CarbonImmutable;

/**
 * Kueri publik pelanggan untuk aplikasi salesman (Modul Salesman bagian 1, §9.7, SLS-11; §19 "Sales/Salesman":
 * pelanggan & piutang pelanggan). Hasil disimpan perangkat untuk kerja offline, jadi nomor HP tetap **tersamar**
 * (`NomorHp::Samarkan`, sama dengan `CariPelangganPos`): data pribadi tidak disimpan di HP. Uang string desimal.
 */
final class PelangganSalesman
{
    public const PER_HALAMAN = 50;

    public function __construct(private readonly DaftarTierPelanggan $tier) {}

    /**
     * Pelanggan aktif urut nama, 50 per halaman; `kata` kosong = semua (untuk unduh awal), selain itu cocok nama atau
     * nomor HP. `JumlahPiutangJatuhTempo` = Σ sisa piutang yang jatuh temponya sudah lewat pada [hariIni].
     *
     * @return array{Data: list<array{Uuid: string, Nama: string, NoHp: string, KodeTier: string|null, NamaTier: string|null, LimitKredit: string|null, TerminHari: int, SisaPiutang: string, JumlahPiutangJatuhTempo: string, HariLewatJatuhTempo: int}>, Halaman: int, AdaBerikutnya: bool}
     */
    public function Daftar(string $kata, int $halaman, CarbonImmutable $hariIni): array
    {
        $kata = trim($kata);
        $halaman = max(1, $halaman);
        $angka = (string) preg_replace('/\D+/', '', $kata);
        $cariHp = $kata !== '' && $angka !== '' && preg_match('/^[0-9+() .-]+$/', $kata) === 1;

        $daftar = Pelanggan::query()
            ->where('Status', StatusPelanggan::Aktif->value)
            ->when($kata !== '', fn ($k) => $k->where(
                $cariHp ? 'NoHp' : 'Nama',
                'like',
                PenerapKueriTabel::PolaCari($cariHp ? (NomorHp::Normalisasi($kata) ?? ltrim($angka, '0')) : $kata),
            ))
            ->orderBy('Nama')
            ->orderBy('Id')
            ->offset(($halaman - 1) * self::PER_HALAMAN)
            ->limit(self::PER_HALAMAN + 1)
            ->get(['Id', 'Uuid', 'Nama', 'NoHp', 'IdTier', 'LimitKredit', 'TerminHari']);

        $adaBerikutnya = $daftar->count() > self::PER_HALAMAN;
        $daftar = $daftar->take(self::PER_HALAMAN)->values();
        $tier = $this->tier->AmbilPeta(array_values(array_filter($daftar->pluck('IdTier')->all(), 'is_int')));
        $piutang = $this->RingkasPiutang(array_values(array_map('intval', $daftar->pluck('Id')->all())), $hariIni);

        return [
            'Data' => array_values($daftar->map(fn (Pelanggan $p): array => [
                'Uuid' => $p->Uuid,
                'Nama' => $p->Nama,
                'NoHp' => NomorHp::Samarkan($p->NoHp),
                'KodeTier' => $p->IdTier === null ? null : ($tier[$p->IdTier]['Kode'] ?? null),
                'NamaTier' => $p->IdTier === null ? null : ($tier[$p->IdTier]['Nama'] ?? null),
                'LimitKredit' => $p->LimitKredit === null ? null : (string) $p->LimitKredit,
                'TerminHari' => $p->TerminHari,
                'SisaPiutang' => $piutang[$p->Id]['Sisa'] ?? '0.00',
                'JumlahPiutangJatuhTempo' => $piutang[$p->Id]['JatuhTempo'] ?? '0.00',
                'HariLewatJatuhTempo' => $piutang[$p->Id]['HariLewat'] ?? 0,
            ])->all()),
            'Halaman' => $halaman,
            'AdaBerikutnya' => $adaBerikutnya,
        ];
    }

    /** Id pelanggan dari Uuid (aktif maupun tidak); null bila bukan milik tenant aktif. */
    public function CariId(string $uuid): ?int
    {
        $id = Pelanggan::query()->where('Uuid', $uuid)->value('Id');

        return is_int($id) ? $id : null;
    }

    /**
     * @param  list<string>  $uuid
     * @return array<string, int> Uuid → Id
     */
    public function PetaId(array $uuid): array
    {
        $hasil = [];

        foreach ($uuid === [] ? [] : Pelanggan::query()->whereIn('Uuid', $uuid)->get(['Id', 'Uuid']) as $p) {
            $hasil[$p->Uuid] = $p->Id;
        }

        return $hasil;
    }

    /**
     * Piutang terbuka satu pelanggan (kasir tempo & faktur grosir), jatuh tempo terdekat dulu. `UmurHari` = hari sejak
     * jatuh tempo (negatif = belum jatuh tempo).
     *
     * @return list<array{Uuid: string, Nomor: string, Tanggal: string, JatuhTempo: string, Jumlah: string, Sisa: string, UmurHari: int, Status: string}>
     */
    public function PiutangTerbuka(int $idPelanggan, CarbonImmutable $hariIni): array
    {
        $hari = CarbonImmutable::parse($hariIni->toDateString(), 'UTC');

        return array_values(Piutang::query()
            ->where('IdPelanggan', $idPelanggan)
            ->whereIn('Status', [StatusPiutang::BelumLunas->value, StatusPiutang::DibayarSebagian->value])
            ->orderBy('JatuhTempo')
            ->orderBy('Id')
            ->get()
            ->map(fn (Piutang $p): array => [
                'Uuid' => $p->Uuid,
                'Nomor' => $p->Nomor,
                'Tanggal' => $p->TanggalBisnis->format('Y-m-d'),
                'JatuhTempo' => $p->JatuhTempo->format('Y-m-d'),
                'Jumlah' => Uang::Dari($p->Jumlah)->KeString(),
                'Sisa' => $p->AmbilSisa()->KeString(),
                'UmurHari' => (int) CarbonImmutable::parse($p->JatuhTempo->toDateString(), 'UTC')->diffInDays($hari, false),
                'Status' => $p->Status->value,
            ])->all());
    }

    /**
     * @param  list<int>  $idPelanggan
     * @return array<int, array{Sisa: string, JatuhTempo: string, HariLewat: int}>
     */
    private function RingkasPiutang(array $idPelanggan, CarbonImmutable $hariIni): array
    {
        if ($idPelanggan === []) {
            return [];
        }

        $hari = CarbonImmutable::parse($hariIni->toDateString(), 'UTC');
        $akumulasi = [];

        $terbuka = Piutang::query()
            ->whereIn('IdPelanggan', $idPelanggan)
            ->whereIn('Status', [StatusPiutang::BelumLunas->value, StatusPiutang::DibayarSebagian->value])
            ->get();

        foreach ($terbuka as $p) {
            $id = (int) $p->IdPelanggan;
            $akumulasi[$id] ??= ['Sisa' => Uang::Nol(), 'JatuhTempo' => Uang::Nol(), 'HariLewat' => 0];
            $sisa = $p->AmbilSisa();
            $lewat = (int) CarbonImmutable::parse($p->JatuhTempo->toDateString(), 'UTC')->diffInDays($hari, false);
            $akumulasi[$id]['Sisa'] = $akumulasi[$id]['Sisa']->Tambah($sisa);

            if ($lewat > 0) {
                $akumulasi[$id]['JatuhTempo'] = $akumulasi[$id]['JatuhTempo']->Tambah($sisa);
                $akumulasi[$id]['HariLewat'] = max($akumulasi[$id]['HariLewat'], $lewat);
            }
        }

        return array_map(fn (array $a): array => [
            'Sisa' => $a['Sisa']->KeString(),
            'JatuhTempo' => $a['JatuhTempo']->KeString(),
            'HariLewat' => $a['HariLewat'],
        ], $akumulasi);
    }
}
