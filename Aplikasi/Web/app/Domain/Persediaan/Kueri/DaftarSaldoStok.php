<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
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
 * Daftar saldo stok per (produk, lokasi stok) untuk `TabelData` (D-16) + ringkasan (tipe FE `PropsSaldoStok`,
 * DesainF05a C.8).
 *
 * Cari: bagian Nama/SKU, atau SKU/barcode/nama persis. Saring: `Gudang` (Uuid; hanya lokasi yang boleh diakses,
 * lokasi lain = daftar kosong), `Keadaan` (Ada/Nol/Minus). Urut: `Nama`, `Nilai`, `Jumlah` (naik/turun), lalu produk &
 * lokasi. Lokasi yang diarsipkan tetap tampil (`GudangAktif`).
 *
 * Saringan, urutan, ringkasan, dan paginasi dikerjakan di SQL: nama produk (milik Katalog) digabung lewat subkueri
 * publik `InfoProdukStok::KueriIdNama()`, urutan lokasi (sedikit baris, milik Organisasi) lewat `FIND_IN_SET()` atas daftar
 * yang sudah diurutkan di PHP. Hanya baris satu halaman yang dimuat; ringkasan dijumlah MySQL atas DECIMAL (eksak).
 */
final class DaftarSaldoStok
{
    public const KOLOM_URUT = ['Nama', 'Nilai', 'Jumlah'];

    public const KOLOM_SARING = ['Gudang', 'Keadaan'];

    private const KEADAAN = ['Ada', 'Nol', 'Minus'];

    public function __construct(
        private readonly InfoProdukStok $infoProduk,
        private readonly InfoGudang $infoGudang,
        private readonly RincianBatchSaldo $rincianBatch,
    ) {}

    /**
     * Saldo untuk `TabelData` (D-16) beserta `Ringkasan` untuk saring yang sama.
     *
     * @param  list<int>|null  $idOutletBoleh  null = semua outlet
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}, Ringkasan: array{TotalNilai: string, JumlahBaris: int, JumlahMinus: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan, ?array $idOutletBoleh): array
    {
        $gudang = $this->AmbilGudangTersaring($permintaan->saring['Gudang'] ?? null, $idOutletBoleh);
        $keadaan = $permintaan->saring['Keadaan'] ?? '';
        $keadaan = in_array($keadaan, self::KEADAAN, true) ? $keadaan : 'Semua';
        $perHalaman = $permintaan->perHalaman;

        if ($gudang === []) {
            return $this->BuatHasil([], 1, $perHalaman, 0, '0', 0);
        }

        $kueri = $this->BuatKueri(array_keys($gudang), $permintaan->cari, $keadaan);
        $ringkasan = (clone $kueri)->toBase()
            ->selectRaw('COUNT(*) AS JumlahBaris')
            ->selectRaw('COALESCE(SUM(`SaldoStok`.`NilaiPersediaan`), 0) AS TotalNilai')
            ->selectRaw('COALESCE(SUM(CASE WHEN `SaldoStok`.`JumlahTersedia` < 0 THEN 1 ELSE 0 END), 0) AS JumlahMinus')
            ->first();

        $jumlah = (int) ($ringkasan->JumlahBaris ?? 0);
        $terakhir = max(1, intdiv($jumlah + $perHalaman - 1, $perHalaman));
        $halaman = min($permintaan->halaman, $terakhir);

        $this->Urutkan($kueri, $permintaan->urut, $gudang);
        $potongan = $kueri->offset(($halaman - 1) * $perHalaman)->limit($perHalaman)->get(['SaldoStok.*']);

        return $this->BuatHasil(
            $this->Petakan($potongan, $gudang),
            $halaman,
            $perHalaman,
            $jumlah,
            (string) ($ringkasan->TotalNilai ?? '0'),
            (int) ($ringkasan->JumlahMinus ?? 0),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $data
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}, Ringkasan: array{TotalNilai: string, JumlahBaris: int, JumlahMinus: int}}
     */
    private function BuatHasil(array $data, int $halaman, int $perHalaman, int $jumlah, string $totalNilai, int $jumlahMinus): array
    {
        return [
            'Data' => $data,
            'Meta' => [
                'Halaman' => $halaman,
                'PerHalaman' => $perHalaman,
                'Total' => $jumlah,
                'JumlahHalaman' => max(1, intdiv($jumlah + $perHalaman - 1, $perHalaman)),
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
     * Urutan pilihan pengguna (`Nama`, `Nilai`, `Jumlah`, naik/turun), lalu nama produk, produk, nama outlet & lokasi,
     * dan Id (urutan stabil).
     *
     * @param  EloquentBuilder<SaldoStok>  $kueri
     * @param  list<array{Kolom: string, Turun: bool}>  $urut
     * @param  array<int, DataInfoGudang>  $gudang
     */
    private function Urutkan(EloquentBuilder $kueri, array $urut, array $gudang): void
    {
        foreach ($urut as $satu) {
            $arah = $satu['Turun'] ? 'desc' : 'asc';
            match ($satu['Kolom']) {
                'Nilai' => $kueri->orderBy('SaldoStok.NilaiPersediaan', $arah),
                'Jumlah' => $kueri->orderBy('SaldoStok.JumlahTersedia', $arah),
                'Nama' => $kueri->orderBy('ProdukSaldo.Nama', $arah),
                default => null,
            };
        }

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
