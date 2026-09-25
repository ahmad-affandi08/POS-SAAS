<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Kueri;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Katalog\Kueri\ProdukUntukLaporan;
use App\Domain\Organisasi\Data\DataInfoGudang;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Persediaan\Kueri\StokUntukLaporan;
use Carbon\CarbonImmutable;

/**
 * Laporan stok F-14a (`/kelola/laporan/stok`, izin `persediaan.lihat`, lokasi stok di outlet yang boleh diakses):
 * - nilai persediaan per lokasi stok dan per kategori pada akhir tanggal bisnis tertentu (dari `MutasiStok`);
 * - stok kritis: saldo terkini ≤ batas minimum per lokasi stok (`ProdukGudang.StokMinimum`), urut kekurangan terbesar.
 * Posisi & kartu stok per produk sudah ada di F-05a.
 */
final class LaporanStok
{
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
}
