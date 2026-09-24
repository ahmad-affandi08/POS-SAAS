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
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Daftar saldo stok per (produk, lokasi stok) berhalaman + ringkasan (tipe FE `PropsSaldoStok` bagian `Saldo` dan
 * `Ringkasan`, DesainF05a C.8).
 *
 * Saringan: `Kata` (bagian Nama/SKU, atau SKU/barcode/nama persis), `UuidGudang` (hanya lokasi yang boleh diakses;
 * lokasi lain = daftar kosong), `Keadaan` (Semua/Ada/Nol/Minus). Urutan: `Nama` (produk lalu lokasi), `-Nilai`
 * (nilai persediaan terbesar), `Jumlah` (jumlah terkecil). Lokasi yang diarsipkan tetap tampil (`GudangAktif`).
 *
 * Saringan, urutan, ringkasan, dan paginasi dikerjakan di SQL: nama produk (milik Katalog) digabung lewat subkueri
 * publik `InfoProdukStok::KueriIdNama()`, urutan lokasi (sedikit baris, milik Organisasi) lewat `FIND_IN_SET()` atas daftar
 * yang sudah diurutkan di PHP. Hanya baris satu halaman yang dimuat; ringkasan dijumlah MySQL atas DECIMAL (eksak).
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
        $perHalaman = max(1, (int) config('persediaan.Saldo.PerHalaman', 50));

        if ($gudang === []) {
            return $this->BuatHasil([], 1, 1, 0, '0', 0);
        }

        $kueri = $this->BuatKueri(array_keys($gudang), $saring['Kata'], $saring['Keadaan']);
        $ringkasan = (clone $kueri)->toBase()
            ->selectRaw('COUNT(*) AS JumlahBaris')
            ->selectRaw('COALESCE(SUM(`SaldoStok`.`NilaiPersediaan`), 0) AS TotalNilai')
            ->selectRaw('COALESCE(SUM(CASE WHEN `SaldoStok`.`JumlahTersedia` < 0 THEN 1 ELSE 0 END), 0) AS JumlahMinus')
            ->first();

        $jumlah = (int) ($ringkasan->JumlahBaris ?? 0);
        $terakhir = max(1, intdiv($jumlah + $perHalaman - 1, $perHalaman));
        $halaman = min(max(1, $halaman), $terakhir);

        $this->Urutkan($kueri, $saring['Urut'], $gudang);
        $potongan = $kueri->offset(($halaman - 1) * $perHalaman)->limit($perHalaman)->get(['SaldoStok.*']);

        return $this->BuatHasil(
            $this->Petakan($potongan, $gudang),
            $halaman,
            $terakhir,
            $jumlah,
            (string) ($ringkasan->TotalNilai ?? '0'),
            (int) ($ringkasan->JumlahMinus ?? 0),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $data
     * @return array{Saldo: array{Data: list<array<string, mixed>>, HalamanSaatIni: int, HalamanTerakhir: int, Total: int}, Ringkasan: array{TotalNilai: string, JumlahBaris: int, JumlahMinus: int}}
     */
    private function BuatHasil(array $data, int $halaman, int $terakhir, int $jumlah, string $totalNilai, int $jumlahMinus): array
    {
        return [
            'Saldo' => [
                'Data' => $data,
                'HalamanSaatIni' => $halaman,
                'HalamanTerakhir' => $terakhir,
                'Total' => $jumlah,
            ],
            'Ringkasan' => [
                'TotalNilai' => (string) BigDecimal::of($totalNilai)->toScale(2),
                'JumlahBaris' => $jumlah,
                'JumlahMinus' => $jumlahMinus,
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
     * Saldo di lokasi terpilih, digabung dengan produk yang cocok dengan kata (baris tanpa produk cocok tersaring).
     *
     * @param  list<int>  $idGudang
     * @return EloquentBuilder<SaldoStok>
     */
    private function BuatKueri(array $idGudang, string $kata, string $keadaan): EloquentBuilder
    {
        return SaldoStok::query()
            ->joinSub($this->infoProduk->KueriIdNama($kata), 'ProdukSaldo', 'ProdukSaldo.Id', '=', 'SaldoStok.IdProduk')
            ->whereIn('SaldoStok.IdGudang', $idGudang)
            ->when($keadaan === 'Ada', fn ($kueri) => $kueri->where('SaldoStok.JumlahTersedia', '>', 0))
            ->when($keadaan === 'Nol', fn ($kueri) => $kueri->where('SaldoStok.JumlahTersedia', '=', 0))
            ->when($keadaan === 'Minus', fn ($kueri) => $kueri->where('SaldoStok.JumlahTersedia', '<', 0));
    }

    /**
     * Urutan utama sesuai saringan, lalu nama produk, produk, nama outlet & lokasi, dan Id (urutan stabil).
     *
     * @param  EloquentBuilder<SaldoStok>  $kueri
     * @param  array<int, DataInfoGudang>  $gudang
     */
    private function Urutkan(EloquentBuilder $kueri, string $urut, array $gudang): void
    {
        match ($urut) {
            '-Nilai' => $kueri->orderByDesc('SaldoStok.NilaiPersediaan'),
            'Jumlah' => $kueri->orderBy('SaldoStok.JumlahTersedia'),
            default => null,
        };

        $urutGudang = array_values($gudang);
        usort($urutGudang, fn (DataInfoGudang $a, DataInfoGudang $b): int => [(string) $a->namaOutlet, $a->nama, $a->id] <=> [(string) $b->namaOutlet, $b->nama, $b->id]);
        // Posisi lokasi pada daftar terurut, dikirim sebagai satu binding ("3,1,2") untuk FIND_IN_SET.
        $daftarId = implode(',', array_map(fn (DataInfoGudang $g): int => $g->id, $urutGudang));

        $kueri->orderBy('ProdukSaldo.Nama')
            ->orderBy('SaldoStok.IdProduk')
            ->orderByRaw('FIND_IN_SET(`SaldoStok`.`IdGudang`, ?)', [$daftarId])
            ->orderBy('SaldoStok.Id');
    }

    /**
     * Baris halaman (tipe FE `BarisSaldoStok`) tanpa N+1: produk satu halaman diambil sekali lewat Katalog; batch dan
     * jumlah nomor seri hanya untuk produk yang dilacak.
     *
     * @param  Collection<int, SaldoStok>  $potongan
     * @param  array<int, DataInfoGudang>  $gudang
     * @return list<array<string, mixed>>
     */
    private function Petakan(Collection $potongan, array $gudang): array
    {
        if ($potongan->isEmpty()) {
            return [];
        }

        $produk = $this->infoProduk->AmbilBanyak(array_values(array_unique($potongan->pluck('IdProduk')->all())), true);
        $pasanganBatch = [];
        $pasanganSeri = [];

        foreach ($potongan as $s) {
            $pelacakan = ($produk[$s->IdProduk] ?? null)?->pelacakan;

            if ($pelacakan === PelacakanProduk::Batch) {
                $pasanganBatch[] = [$s->IdProduk, $s->IdGudang];
            } elseif ($pelacakan === PelacakanProduk::Seri) {
                $pasanganSeri[] = [$s->IdProduk, $s->IdGudang];
            }
        }

        $batch = $pasanganBatch === [] ? [] : $this->rincianBatch->UntukPasangan($pasanganBatch);
        $seri = $pasanganSeri === [] ? [] : $this->rincianBatch->HitungSeriTersedia($pasanganSeri);
        $hasil = [];

        foreach ($potongan as $s) {
            $p = $produk[$s->IdProduk] ?? null;
            $g = $gudang[$s->IdGudang] ?? null;

            if (! $p instanceof DataInfoProdukStok || ! $g instanceof DataInfoGudang) {
                continue;
            }

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
