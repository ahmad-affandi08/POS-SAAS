<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Kueri;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Katalog\Kueri\ProdukUntukLaporan;
use App\Domain\Organisasi\Data\DataInfoGudang;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Persediaan\Kueri\StokUntukLaporan;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;

/**
 * Laporan stok F-14a (`/kelola/laporan/stok`, izin `persediaan.lihat`, lokasi stok di outlet yang boleh diakses):
 * - nilai persediaan per lokasi stok dan per kategori pada akhir tanggal bisnis tertentu (dari `MutasiStok`; tanpa
 *   barang konsinyasi, F-05i);
 * - stok kritis: saldo terkini ≤ batas minimum per lokasi stok (`ProdukGudang.StokMinimum`), urut kekurangan terbesar.
 * Posisi & kartu stok per produk sudah ada di F-05a.
 */
final class LaporanStok
{
    public const HARI_MUKA_KEDALUWARSA = 30;

    public const HARI_SEGERA_KEDALUWARSA = 7;

    /** X6: panjang periode dasar rata-rata pemakaian untuk saran restock. */
    public const HARI_DASAR_RESTOCK = 28;

    public function __construct(
        private readonly InfoGudang $infoGudang,
        private readonly StokUntukLaporan $stok,
        private readonly ProdukUntukLaporan $produk,
        private readonly InfoProdukStok $infoProduk,
    ) {}

    /**
     * Lokasi stok yang boleh diakses (termasuk diarsipkan), disaring satu Uuid bila diisi.
     *
     * @param  list<int>|null  $idOutletBoleh
     * @return array<int, DataInfoGudang>
     */
    public function AmbilGudang(?array $idOutletBoleh, string $uuidGudang = ''): array
    {
        $hasil = [];

        foreach ($this->infoGudang->AmbilBoleh($idOutletBoleh, false) as $g) {
            if ($uuidGudang === '' || $g->uuid === $uuidGudang) {
                $hasil[$g->id] = $g;
            }
        }

        return $hasil;
    }

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Total: array{Nilai: string, JumlahProduk: int}, PerGudang: list<array{Kunci: string, NamaGudang: string, NamaOutlet: string, JumlahProduk: int, Nilai: string}>, PerKategori: list<array{Kunci: string, NamaKategori: string, JumlahProduk: int, Nilai: string}>}
     */
    public function NilaiPersediaan(CarbonImmutable $tanggal, ?array $idOutletBoleh, string $uuidGudang = ''): array
    {
        $gudang = $this->AmbilGudang($idOutletBoleh, $uuidGudang);
        $baris = $this->stok->AmbilNilaiPadaTanggal($tanggal, array_keys($gudang));
        // F-05i: barang titipan bukan aset toko (nilainya hutang ke penitip), jadi tidak ikut nilai persediaan.
        $jenis = $this->infoProduk->AmbilBanyak(array_values(array_unique(array_column($baris, 'IdProduk'))), denganTerhapus: true);
        $baris = array_values(array_filter($baris, fn (array $b): bool => ($jenis[$b['IdProduk']] ?? null)?->jenis !== JenisProduk::Konsinyasi));
        $kategori = $this->produk->AmbilKategori(array_values(array_unique(array_column($baris, 'IdProduk'))));
        $perGudang = [];
        $perKategori = [];
        $total = Uang::Nol();
        $produkTotal = [];

        foreach ($baris as $b) {
            $nilai = Uang::Dari($b['Nilai']);
            $total = $total->Tambah($nilai);
            $produkTotal[$b['IdProduk']] = true;
            $g = $gudang[$b['IdGudang']] ?? null;
            $kunciGudang = $g->uuid ?? (string) $b['IdGudang'];
            $perGudang[$kunciGudang] ??= ['Kunci' => $kunciGudang, 'NamaGudang' => $g->nama ?? '', 'NamaOutlet' => $g->namaOutlet ?? '', 'Produk' => [], 'Nilai' => Uang::Nol()];
            $perGudang[$kunciGudang]['Produk'][$b['IdProduk']] = true;
            $perGudang[$kunciGudang]['Nilai'] = $perGudang[$kunciGudang]['Nilai']->Tambah($nilai);

            $k = $kategori[$b['IdProduk']] ?? ['IdKategori' => null, 'NamaKategori' => ''];
            $kunciKategori = (string) ($k['IdKategori'] ?? 0);
            $perKategori[$kunciKategori] ??= ['Kunci' => $kunciKategori, 'NamaKategori' => $k['IdKategori'] === null ? 'Tanpa kategori' : $k['NamaKategori'], 'Produk' => [], 'Nilai' => Uang::Nol()];
            $perKategori[$kunciKategori]['Produk'][$b['IdProduk']] = true;
            $perKategori[$kunciKategori]['Nilai'] = $perKategori[$kunciKategori]['Nilai']->Tambah($nilai);
        }

        $urut = fn (array $a, array $b): int => Uang::Dari($b['Nilai'])->Bandingkan(Uang::Dari($a['Nilai']));
        $hasilGudang = array_values(array_map(fn (array $g): array => [
            'Kunci' => $g['Kunci'],
            'NamaGudang' => $g['NamaGudang'],
            'NamaOutlet' => $g['NamaOutlet'],
            'JumlahProduk' => count($g['Produk']),
            'Nilai' => $g['Nilai']->KeString(),
        ], $perGudang));
        $hasilKategori = array_values(array_map(fn (array $g): array => [
            'Kunci' => $g['Kunci'],
            'NamaKategori' => $g['NamaKategori'],
            'JumlahProduk' => count($g['Produk']),
            'Nilai' => $g['Nilai']->KeString(),
        ], $perKategori));
        usort($hasilGudang, $urut);
        usort($hasilKategori, $urut);

        return [
            'Total' => ['Nilai' => $total->KeString(), 'JumlahProduk' => count($produkTotal)],
            'PerGudang' => $hasilGudang,
            'PerKategori' => $hasilKategori,
        ];
    }

    /**
     * Stok kritis (saldo ≤ batas minimum). `batas` null = semua.
     *
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Jumlah: int, Baris: list<array{Kunci: string, UuidProduk: string, NamaProduk: string, Sku: string|null, SimbolSatuan: string, UuidGudang: string, NamaGudang: string, NamaOutlet: string, Saldo: string, StokMinimum: string, Kekurangan: string}>}
     */
    public function StokKritis(?array $idOutletBoleh, string $uuidGudang = '', ?int $batas = null): array
    {
        $gudang = $this->AmbilGudang($idOutletBoleh, $uuidGudang);
        $minimum = $this->produk->AmbilBatasMinimum(array_keys($gudang));
        $saldo = $this->stok->AmbilSaldo(array_keys($gudang), array_values(array_unique(array_column($minimum, 'IdProduk'))));
        $kritis = [];

        foreach ($minimum as $m) {
            $jumlah = Kuantitas::Dari($saldo["{$m['IdProduk']}|{$m['IdGudang']}"]['Jumlah'] ?? '0');
            $min = Kuantitas::Dari($m['StokMinimum']);

            if ($jumlah->Bandingkan($min) <= 0) {
                $kritis[] = [$m, $jumlah, $min];
            }
        }

        usort($kritis, fn (array $a, array $b): int => $b[2]->Kurangi($b[1])->Bandingkan($a[2]->Kurangi($a[1])) ?: $a[0]['IdProduk'] <=> $b[0]['IdProduk']);
        $jumlahKritis = count($kritis);

        if ($batas !== null) {
            $kritis = array_slice($kritis, 0, $batas);
        }

        $info = $this->infoProduk->AmbilBanyak(array_values(array_unique(array_map(fn (array $k): int => $k[0]['IdProduk'], $kritis))));
        $baris = [];

        foreach ($kritis as [$m, $jumlah, $min]) {
            $p = $info[$m['IdProduk']] ?? null;
            $g = $gudang[$m['IdGudang']] ?? null;
            $baris[] = [
                'Kunci' => ($p->uuid ?? (string) $m['IdProduk']).'-'.($g->uuid ?? (string) $m['IdGudang']),
                'UuidProduk' => $p->uuid ?? '',
                'NamaProduk' => $p->nama ?? 'Produk tidak dikenal',
                'Sku' => $p?->sku,
                'SimbolSatuan' => $p->simbolSatuan ?? '',
                'UuidGudang' => $g->uuid ?? '',
                'NamaGudang' => $g->nama ?? '',
                'NamaOutlet' => $g->namaOutlet ?? '',
                'Saldo' => $jumlah->KeString(),
                'StokMinimum' => $min->KeString(),
                'Kekurangan' => $min->Kurangi($jumlah)->KeString(),
            ];
        }

        return ['Jumlah' => $jumlahKritis, 'Baris' => $baris];
    }

    /**
     * X6 (v3.43) saran restock: rata-rata pemakaian harian `HARI_DASAR_RESTOCK` hari terakhir (sampai kemarin) per produk
     * × lokasi stok (`StokUntukLaporan::AmbilPemakaian`), saldo terkini, perkiraan hari stok habis, dan saran beli =
     * rata-rata × `$hariCakupan` − saldo (dibulatkan ke atas ke satuan bulat bila satuannya tidak desimal; minimal 0).
     * Urut dari yang paling cepat habis. Hanya produk yang dipakai pada periode dasar.
     *
     * @param  list<int>|null  $idOutletBoleh
     * @return array{HariDasar: int, HariCakupan: int, Baris: list<array<string, mixed>>}
     */
    public function SaranRestock(?array $idOutletBoleh, CarbonImmutable $hariIni, string $uuidGudang = '', int $hariCakupan = 14): array
    {
        $gudang = $this->AmbilGudang($idOutletBoleh, $uuidGudang);
        $sampai = $hariIni->subDay();
        $pakai = $this->stok->AmbilPemakaian(array_keys($gudang), $sampai->subDays(self::HARI_DASAR_RESTOCK - 1), $sampai);
        $saldo = $this->stok->AmbilSaldo(array_keys($gudang), array_values(array_unique(array_column($pakai, 'IdProduk'))));
        $info = $this->infoProduk->AmbilBanyak(array_values(array_unique(array_column($pakai, 'IdProduk'))));
        $baris = [];

        foreach ($pakai as $b) {
            $p = $info[$b['IdProduk']] ?? null;
            $g = $gudang[$b['IdGudang']] ?? null;

            if ($p === null || $p->jenis === JenisProduk::Konsinyasi) {
                continue;
            }

            $stok = BigDecimal::of($saldo["{$b['IdProduk']}|{$b['IdGudang']}"]['Jumlah'] ?? '0');
            $rata = BigDecimal::of($b['Pakai'])->dividedBy(self::HARI_DASAR_RESTOCK, 4, RoundingMode::HalfUp);
            $butuh = $rata->multipliedBy($hariCakupan)->minus($stok);
            $saran = $butuh->isPositive() ? $butuh->toScale($p->bolehDesimal ? 4 : 0, RoundingMode::Up) : BigDecimal::zero()->toScale(4);
            $hariHabis = $rata->isZero() ? null : ($stok->isPositive() ? $stok->dividedBy($rata, 0, RoundingMode::Down)->toInt() : 0);
            $baris[] = [
                'Kunci' => $p->uuid.'-'.($g->uuid ?? (string) $b['IdGudang']),
                'UuidProduk' => $p->uuid,
                'NamaProduk' => $p->nama,
                'Sku' => $p->sku,
                'SimbolSatuan' => $p->simbolSatuan,
                'UuidGudang' => $g->uuid ?? '',
                'NamaGudang' => $g->nama ?? '',
                'NamaOutlet' => $g->namaOutlet ?? '',
                'Pakai' => $b['Pakai'],
                'RataPerHari' => (string) $rata,
                'Saldo' => (string) $stok->toScale(4),
                'HariHabis' => $hariHabis,
                'SaranBeli' => (string) $saran->toScale(4),
            ];
        }

        usort($baris, fn (array $a, array $b): int => ($a['HariHabis'] ?? PHP_INT_MAX) <=> ($b['HariHabis'] ?? PHP_INT_MAX) ?: strcmp((string) $a['NamaProduk'], (string) $b['NamaProduk']));

        return ['HariDasar' => self::HARI_DASAR_RESTOCK, 'HariCakupan' => $hariCakupan, 'Baris' => $baris];
    }

    /**
     * F-05g: batch yang sudah lewat atau akan kedaluwarsa dalam `$hariMuka` hari (bawaan 30), per lokasi stok.
     * `SisaHari` negatif = sudah lewat. `Status`: `Lewat`, `Segera` (≤ 7 hari), `Mendekati`.
     *
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Jumlah: int, JumlahLewat: int, Baris: list<array{Kunci: string, UuidProduk: string, NamaProduk: string, Sku: string|null, SimbolSatuan: string, UuidGudang: string, NamaGudang: string, NamaOutlet: string, NomorBatch: string, TanggalKedaluwarsa: string, SisaHari: int, Status: string, Sisa: string}>}
     */
    public function BatchKedaluwarsa(?array $idOutletBoleh, CarbonImmutable $hariIni, string $uuidGudang = '', int $hariMuka = self::HARI_MUKA_KEDALUWARSA, ?int $batas = null): array
    {
        $gudang = $this->AmbilGudang($idOutletBoleh, $uuidGudang);
        $hasil = $this->stok->AmbilBatchMendekatiKedaluwarsa(array_keys($gudang), $hariIni->addDays($hariMuka), $batas);
        $info = $this->infoProduk->AmbilBanyak(array_values(array_unique(array_column($hasil['Baris'], 'IdProduk'))));
        $lewat = $this->stok->AmbilBatchMendekatiKedaluwarsa(array_keys($gudang), $hariIni->subDay(), 0)['Jumlah'];
        $baris = [];

        foreach ($hasil['Baris'] as $b) {
            $p = $info[$b['IdProduk']] ?? null;
            $g = $gudang[$b['IdGudang']] ?? null;
            $sisaHari = (int) $hariIni->startOfDay()->diffInDays(CarbonImmutable::parse($b['TanggalKedaluwarsa'], $hariIni->getTimezone())->startOfDay(), false);
            $baris[] = [
                'Kunci' => ($p->uuid ?? (string) $b['IdProduk']).'-'.($g->uuid ?? (string) $b['IdGudang']).'-'.$b['NomorBatch'],
                'UuidProduk' => $p->uuid ?? '',
                'NamaProduk' => $p->nama ?? 'Produk tidak dikenal',
                'Sku' => $p?->sku,
                'SimbolSatuan' => $p->simbolSatuan ?? '',
                'UuidGudang' => $g->uuid ?? '',
                'NamaGudang' => $g->nama ?? '',
                'NamaOutlet' => $g->namaOutlet ?? '',
                'NomorBatch' => $b['NomorBatch'],
                'TanggalKedaluwarsa' => $b['TanggalKedaluwarsa'],
                'SisaHari' => $sisaHari,
                'Status' => $sisaHari < 0 ? 'Lewat' : ($sisaHari <= self::HARI_SEGERA_KEDALUWARSA ? 'Segera' : 'Mendekati'),
                'Sisa' => $b['JumlahSisa'],
            ];
        }

        return ['Jumlah' => $hasil['Jumlah'], 'JumlahLewat' => $lewat, 'Baris' => $baris];
    }
}
