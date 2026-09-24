<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Katalog\Data\DataInfoProdukStok;
use App\Domain\Katalog\Enum\PelacakanProduk;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Data\DataInfoGudang;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Persediaan\Model\SaldoStok;
use Brick\Math\BigDecimal;

/**
 * Daftar saldo stok per (produk, lokasi stok) berhalaman + ringkasan (tipe FE `PropsSaldoStok` bagian `Saldo` dan
 * `Ringkasan`, DesainF05a C.8).
 *
 * Saringan: `Kata` (bagian Nama/SKU, atau SKU/barcode/nama persis), `UuidGudang` (hanya lokasi yang boleh diakses;
 * lokasi lain = daftar kosong), `Keadaan` (Semua/Ada/Nol/Minus). Urutan: `Nama` (produk lalu lokasi), `-Nilai`
 * (nilai persediaan terbesar), `Jumlah` (jumlah terkecil). Lokasi yang diarsipkan tetap tampil (`GudangAktif`).
 *
 * Nama/SKU produk milik domain Katalog, jadi saringan kata dan urut nama dikerjakan di PHP atas baris saldo ringan
 * (tanpa kueri tabel Katalog); ringkasan dijumlah dengan BigDecimal (eksak).
 */
final class DaftarSaldoStok
{
    private const KEADAAN = ['Semua', 'Ada', 'Nol', 'Minus'];

    private const URUT = ['Nama', '-Nilai', 'Jumlah'];

    public function __construct(
        private readonly InfoProdukStok $infoProduk,
        private readonly InfoGudang $infoGudang,
        private readonly RincianBatchSaldo $rincianBatch,
    ) {}

    /**
     * Saringan dari parameter query yang sudah dibersihkan (nilai tak dikenal → bawaan).
     *
     * @return array{Kata: string, UuidGudang: string|null, Keadaan: string, Urut: string}
     */
    public static function NormalkanSaring(mixed $kata, mixed $uuidGudang, mixed $keadaan, mixed $urut): array
    {
        return [
            'Kata' => is_string($kata) ? mb_substr(trim($kata), 0, 100) : '',
            'UuidGudang' => is_string($uuidGudang) && $uuidGudang !== '' ? $uuidGudang : null,
            'Keadaan' => in_array($keadaan, self::KEADAAN, true) ? $keadaan : 'Semua',
            'Urut' => in_array($urut, self::URUT, true) ? $urut : 'Nama',
        ];
    }

    /**
     * @param  array{Kata: string, UuidGudang: string|null, Keadaan: string, Urut: string}  $saring
     * @param  list<int>|null  $idOutletBoleh  null = semua outlet
     * @return array{Saldo: array{Data: list<array<string, mixed>>, HalamanSaatIni: int, HalamanTerakhir: int, Total: int}, Ringkasan: array{TotalNilai: string, JumlahBaris: int, JumlahMinus: int}}
     */
    public function Ambil(array $saring, ?array $idOutletBoleh, int $halaman): array
    {
        $gudang = $this->AmbilGudangTersaring($saring['UuidGudang'], $idOutletBoleh);
        $ringan = $gudang === [] ? [] : $this->AmbilBarisRingan(array_keys($gudang), $saring['Keadaan']);
        $produk = $this->infoProduk->AmbilBanyak(array_values(array_unique(array_column($ringan, 'IdProduk'))), true);

        $kata = $saring['Kata'];

        if ($kata !== '') {
            $cocok = $this->AmbilIdProdukCocok($kata, $produk);
            $ringan = array_values(array_filter($ringan, fn (array $b): bool => isset($cocok[$b['IdProduk']])));
        }

        $ringan = array_values(array_filter($ringan, fn (array $b): bool => isset($produk[$b['IdProduk']])));
        $this->Urutkan($ringan, $saring['Urut'], $produk, $gudang);

        $total = BigDecimal::zero();
        $minus = 0;

        foreach ($ringan as $baris) {
            $total = $total->plus($baris['NilaiPersediaan']);
            $minus += BigDecimal::of($baris['JumlahTersedia'])->isNegative() ? 1 : 0;
        }

        $perHalaman = max(1, (int) config('persediaan.Saldo.PerHalaman', 50));
        $jumlah = count($ringan);
        $terakhir = max(1, intdiv($jumlah + $perHalaman - 1, $perHalaman));
        $halaman = min(max(1, $halaman), $terakhir);
        $potongan = array_slice($ringan, ($halaman - 1) * $perHalaman, $perHalaman);

        return [
            'Saldo' => [
                'Data' => $this->Petakan($potongan, $produk, $gudang),
                'HalamanSaatIni' => $halaman,
                'HalamanTerakhir' => $terakhir,
                'Total' => $jumlah,
            ],
            'Ringkasan' => [
                'TotalNilai' => (string) $total->toScale(2),
                'JumlahBaris' => $jumlah,
                'JumlahMinus' => $minus,
            ],
        ];
    }

    /**
     * Lokasi stok yang boleh diakses (termasuk diarsipkan), dipersempit ke satu lokasi bila disaring.
     *
     * @param  list<int>|null  $idOutletBoleh
     * @return array<int, DataInfoGudang>
     */
    private function AmbilGudangTersaring(?string $uuidGudang, ?array $idOutletBoleh): array
    {
        $hasil = [];

        foreach ($this->infoGudang->AmbilBoleh($idOutletBoleh, false) as $g) {
            if ($uuidGudang === null || $g->uuid === $uuidGudang) {
                $hasil[$g->id] = $g;
            }
        }

        return $hasil;
    }

    /**
     * @param  list<int>  $idGudang
     * @return list<array{Id: int, IdProduk: int, IdGudang: int, JumlahTersedia: string, NilaiPersediaan: string}>
     */
    private function AmbilBarisRingan(array $idGudang, string $keadaan): array
    {
        $baris = SaldoStok::query()
            ->whereIn('IdGudang', $idGudang)
            ->when($keadaan === 'Ada', fn ($kueri) => $kueri->where('JumlahTersedia', '>', 0))
            ->when($keadaan === 'Nol', fn ($kueri) => $kueri->where('JumlahTersedia', '=', 0))
            ->when($keadaan === 'Minus', fn ($kueri) => $kueri->where('JumlahTersedia', '<', 0))
            ->orderBy('Id')
            ->toBase()
            ->get(['Id', 'IdProduk', 'IdGudang', 'JumlahTersedia', 'NilaiPersediaan']);

        return array_values($baris->map(fn (object $b): array => [
            'Id' => (int) $b->Id,
            'IdProduk' => (int) $b->IdProduk,
            'IdGudang' => (int) $b->IdGudang,
            'JumlahTersedia' => (string) $b->JumlahTersedia,
            'NilaiPersediaan' => (string) $b->NilaiPersediaan,
        ])->all());
    }

    /**
     * Produk yang Nama/SKU-nya mengandung kata (tanpa membedakan huruf besar/kecil), atau yang SKU/barcode/nama-nya
     * persis kata (pencocokan Katalog).
     *
     * @param  array<int, DataInfoProdukStok>  $produk
     * @return array<int, true>
     */
    private function AmbilIdProdukCocok(string $kata, array $produk): array
    {
        $kecil = mb_strtolower($kata);
        $cocok = [];

        foreach ($produk as $id => $p) {
            if (str_contains(mb_strtolower($p->nama), $kecil) || ($p->sku !== null && str_contains(mb_strtolower($p->sku), $kecil))) {
                $cocok[$id] = true;
            }
        }

        foreach ($this->infoProduk->CariKunciImpor([$kata])[$kata] ?? [] as $id) {
            $cocok[$id] = true;
        }

        return $cocok;
    }

    /**
     * @param  list<array{Id: int, IdProduk: int, IdGudang: int, JumlahTersedia: string, NilaiPersediaan: string}>  $ringan
     * @param  array<int, DataInfoProdukStok>  $produk
     * @param  array<int, DataInfoGudang>  $gudang
     */
    private function Urutkan(array &$ringan, string $urut, array $produk, array $gudang): void
    {
        $kunciNama = fn (array $b): array => [
            mb_strtolower($produk[$b['IdProduk']]->nama),
            $b['IdProduk'],
            (string) $gudang[$b['IdGudang']]->namaOutlet,
            $gudang[$b['IdGudang']]->nama,
            $b['Id'],
        ];

        usort($ringan, function (array $a, array $b) use ($urut, $kunciNama): int {
            $banding = match ($urut) {
                '-Nilai' => BigDecimal::of($b['NilaiPersediaan'])->compareTo($a['NilaiPersediaan']),
                'Jumlah' => BigDecimal::of($a['JumlahTersedia'])->compareTo($b['JumlahTersedia']),
                default => 0,
            };

            return $banding !== 0 ? $banding : $kunciNama($a) <=> $kunciNama($b);
        });
    }

    /**
     * Baris halaman (tipe FE `BarisSaldoStok`) tanpa N+1: batch dan jumlah nomor seri hanya untuk produk yang
     * dilacak.
     *
     * @param  list<array{Id: int, IdProduk: int, IdGudang: int, JumlahTersedia: string, NilaiPersediaan: string}>  $potongan
     * @param  array<int, DataInfoProdukStok>  $produk
     * @param  array<int, DataInfoGudang>  $gudang
     * @return list<array<string, mixed>>
     */
    private function Petakan(array $potongan, array $produk, array $gudang): array
    {
        if ($potongan === []) {
            return [];
        }

        $saldo = SaldoStok::query()->whereKey(array_column($potongan, 'Id'))->get()->keyBy('Id');
        $pasanganBatch = [];
        $pasanganSeri = [];

        foreach ($potongan as $b) {
            $pelacakan = $produk[$b['IdProduk']]->pelacakan;

            if ($pelacakan === PelacakanProduk::Batch) {
                $pasanganBatch[] = [$b['IdProduk'], $b['IdGudang']];
            } elseif ($pelacakan === PelacakanProduk::Seri) {
                $pasanganSeri[] = [$b['IdProduk'], $b['IdGudang']];
            }
        }

        $batch = $pasanganBatch === [] ? [] : $this->rincianBatch->UntukPasangan($pasanganBatch);
        $seri = $pasanganSeri === [] ? [] : $this->rincianBatch->HitungSeriTersedia($pasanganSeri);
        $hasil = [];

        foreach ($potongan as $b) {
            $s = $saldo->get($b['Id']);

            if (! $s instanceof SaldoStok) {
                continue;
            }

            $p = $produk[$b['IdProduk']];
            $g = $gudang[$b['IdGudang']];
            $kunci = SaldoStok::BuatKunciPasangan($p->id, $g->id);

            $hasil[] = [
                'UuidProduk' => $p->uuid,
                'NamaProduk' => $p->nama,
                'Sku' => $p->sku,
                'SimbolSatuan' => $p->simbolSatuan,
                'Pelacakan' => $p->pelacakan->value,
                'UuidGudang' => $g->uuid,
                'NamaGudang' => $g->nama,
                'NamaOutlet' => $g->namaOutlet,
                'GudangAktif' => $g->aktif,
                'JumlahTersedia' => $s->JumlahTersedia,
                'HppRataRata' => $s->HppRataRata,
                'NilaiPersediaan' => $s->NilaiPersediaan,
                'DiubahPada' => $s->DiubahPada?->toIso8601String(),
                'Batch' => $p->pelacakan === PelacakanProduk::Batch ? ($batch[$kunci] ?? []) : [],
                'JumlahNomorSeri' => $p->pelacakan === PelacakanProduk::Seri ? ($seri[$kunci] ?? 0) : null,
                'TautanKartuStok' => route('kelola.persediaan.kartu-stok', ['produk' => $p->uuid, 'gudang' => $g->uuid]),
            ];
        }

        return $hasil;
    }
}
