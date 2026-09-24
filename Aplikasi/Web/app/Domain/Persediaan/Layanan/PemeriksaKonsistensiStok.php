<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Persediaan\Enum\MetodeHpp;
use App\Domain\Persediaan\Enum\StatusNomorSeri;
use App\Domain\Persediaan\Model\BatchStok;
use App\Domain\Persediaan\Model\LapisanFifo;
use App\Domain\Persediaan\Model\MutasiStok;
use App\Domain\Persediaan\Model\NomorSeri;
use App\Domain\Persediaan\Model\SaldoStok;
use App\Domain\Tenant\Kueri\PengaturanPersediaanTenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/**
 * Memeriksa konsistensi rantai SaldoSetelah/NilaiSetelah, lapisan FIFO, dan status seri tenant aktif (DesainF05a C.9).
 *
 * Hanya melaporkan, tidak memperbaiki. Selain tiga pemeriksaan di atas (yang tidak bisa diperbaiki
 * `PembangunUlangSaldoStok`, H-18), juga melaporkan perbedaan cache yang bisa diperbaiki bangun ulang supaya
 * `persediaan:bangun-ulang-saldo --periksa` mendeteksi saldo rusak: `SaldoStok` ≠ Σ `MutasiStok` (jumlah, nilai,
 * mutasi terakhir, HPP rata-rata terakhir), Q = 0 ⇒ N = 0 dan Q > 0 ⇒ N ≥ 0, serta `BatchStok.JumlahSisa` ≠ Σ.
 * Semua kueri berangkat dari model ber-`MilikTenant` (subkueri ber-scope); perbandingan desimal dilakukan MySQL
 * (DECIMAL, eksak). Setiap pemeriksaan dibatasi `BATAS_PER_PEMERIKSAAN` uraian.
 */
final class PemeriksaKonsistensiStok
{
    public const BATAS_PER_PEMERIKSAAN = 50;

    public function __construct(private readonly PengaturanPersediaanTenant $pengaturan) {}

    /**
     * @return list<string> uraian perbedaan; kosong = konsisten
     */
    public function Periksa(): array
    {
        return [
            ...$this->PeriksaSaldo(),
            ...$this->PeriksaNilaiSaatJumlahNol(),
            ...$this->PeriksaRantaiMutasi(),
            ...($this->pengaturan->Ambil()->metodeHpp === MetodeHpp::Fifo ? $this->PeriksaLapisanFifo() : []),
            ...$this->PeriksaBatch(),
            ...$this->PeriksaNomorSeri(),
        ];
    }

    /**
     * Cache `SaldoStok` terhadap ledger per (produk, lokasi): Σ Jumlah, Σ TotalHpp, Id & HppRataRataSetelah
     * mutasi terakhir. Termasuk mutasi tanpa baris saldo dan baris saldo yatim yang tidak nol.
     *
     * @return list<string>
     */
    private function PeriksaSaldo(): array
    {
        $pasangan = SaldoStok::query()->select(['IdProduk', 'IdGudang'])->toBase()
            ->union(MutasiStok::query()->select(['IdProduk', 'IdGudang'])->distinct()->toBase());

        $ringkasan = MutasiStok::query()->groupBy(['IdProduk', 'IdGudang'])
            ->selectRaw('IdProduk, IdGudang, SUM(Jumlah) AS TotalJumlah, SUM(TotalHpp) AS TotalNilai, MAX(Id) AS IdTerakhir')
            ->toBase();

        $kueri = DB::query()->fromSub($pasangan, 'p')
            ->leftJoinSub(SaldoStok::query()->toBase(), 's', fn (JoinClause $j) => $j->on('s.IdProduk', '=', 'p.IdProduk')->on('s.IdGudang', '=', 'p.IdGudang'))
            ->leftJoinSub($ringkasan, 'm', fn (JoinClause $j) => $j->on('m.IdProduk', '=', 'p.IdProduk')->on('m.IdGudang', '=', 'p.IdGudang'))
            ->leftJoinSub(MutasiStok::query()->select(['Id', 'HppRataRataSetelah'])->toBase(), 't', 't.Id', '=', 'm.IdTerakhir')
            ->whereRaw('(COALESCE(s.JumlahTersedia, 0) <> COALESCE(m.TotalJumlah, 0)
                OR COALESCE(s.NilaiPersediaan, 0) <> COALESCE(m.TotalNilai, 0)
                OR NOT (s.IdMutasiStokTerakhir <=> m.IdTerakhir)
                OR NOT (s.HppRataRata <=> t.HppRataRataSetelah))')
            ->orderBy('p.IdProduk')->orderBy('p.IdGudang')
            ->select(['p.IdProduk', 'p.IdGudang', 's.Id AS IdSaldo', 's.JumlahTersedia', 's.NilaiPersediaan', 's.IdMutasiStokTerakhir', 's.HppRataRata',
                'm.TotalJumlah', 'm.TotalNilai', 'm.IdTerakhir', 't.HppRataRataSetelah']);

        return $this->Uraikan($kueri, fn (array $b): string => $b['IdSaldo'] === null
            ? sprintf('Saldo stok produk %s di lokasi %s belum ada padahal ada mutasi (Σ jumlah %s, Σ nilai %s).',
                self::Teks($b['IdProduk']), self::Teks($b['IdGudang']), self::Teks($b['TotalJumlah']), self::Teks($b['TotalNilai']))
            : sprintf('Saldo stok produk %s di lokasi %s: jumlah %s / nilai %s / mutasi terakhir %s / HPP rata-rata %s, seharusnya %s / %s / %s / %s.',
                self::Teks($b['IdProduk']), self::Teks($b['IdGudang']),
                self::Teks($b['JumlahTersedia']), self::Teks($b['NilaiPersediaan']), self::Teks($b['IdMutasiStokTerakhir']), self::Teks($b['HppRataRata']),
                self::Teks($b['TotalJumlah'] ?? '0'), self::Teks($b['TotalNilai'] ?? '0'), self::Teks($b['IdTerakhir']), self::Teks($b['HppRataRataSetelah'])));
    }

    /**
     * Q = 0 ⇒ N = 0 dan Q > 0 ⇒ N ≥ 0 (DesainF05a C.3).
     *
     * @return list<string>
     */
    private function PeriksaNilaiSaatJumlahNol(): array
    {
        $kueri = SaldoStok::query()
            ->whereRaw('((JumlahTersedia = 0 AND NilaiPersediaan <> 0) OR (JumlahTersedia > 0 AND NilaiPersediaan < 0))')
            ->orderBy('IdProduk')->orderBy('IdGudang')
            ->select(['IdProduk', 'IdGudang', 'JumlahTersedia', 'NilaiPersediaan'])->toBase();

        return $this->Uraikan($kueri, fn (array $b): string => sprintf(
            'Saldo stok produk %s di lokasi %s: jumlah %s dengan nilai %s (jumlah nol harus bernilai nol, jumlah positif tidak boleh bernilai negatif).',
            self::Teks($b['IdProduk']), self::Teks($b['IdGudang']), self::Teks($b['JumlahTersedia']), self::Teks($b['NilaiPersediaan']),
        ));
    }

    /**
     * `SaldoSetelah`/`NilaiSetelah` tiap mutasi = jumlah berjalan urut Id per (produk, lokasi).
     *
     * @return list<string>
     */
    private function PeriksaRantaiMutasi(): array
    {
        $berjalan = MutasiStok::query()
            ->select(['Id', 'IdProduk', 'IdGudang', 'SaldoSetelah', 'NilaiSetelah'])
            ->selectRaw('SUM(Jumlah) OVER (PARTITION BY IdProduk, IdGudang ORDER BY Id) AS Berjalan')
            ->selectRaw('SUM(TotalHpp) OVER (PARTITION BY IdProduk, IdGudang ORDER BY Id) AS NilaiBerjalan')
            ->toBase();

        $kueri = DB::query()->fromSub($berjalan, 'r')
            ->whereRaw('(r.SaldoSetelah <> r.Berjalan OR r.NilaiSetelah <> r.NilaiBerjalan)')
            ->orderBy('r.Id');

        return $this->Uraikan($kueri, fn (array $b): string => sprintf(
            'Mutasi stok %s (produk %s, lokasi %s): saldo setelah %s / nilai setelah %s, seharusnya %s / %s menurut urutan mutasi.',
            self::Teks($b['Id']), self::Teks($b['IdProduk']), self::Teks($b['IdGudang']),
            self::Teks($b['SaldoSetelah']), self::Teks($b['NilaiSetelah']), self::Teks($b['Berjalan']), self::Teks($b['NilaiBerjalan']),
        ));
    }

    /**
     * FIFO: saat Q ≥ 0, Σ JumlahSisa/NilaiSisa lapisan terbuka = saldo; saat Q < 0 tidak ada lapisan bersisa;
     * penanda `Habis` = (JumlahSisa = 0) dan sisa tidak negatif.
     *
     * @return list<string>
     */
    private function PeriksaLapisanFifo(): array
    {
        $sisa = LapisanFifo::query()->where('Habis', false)->groupBy(['IdProduk', 'IdGudang'])
            ->selectRaw('IdProduk, IdGudang, SUM(JumlahSisa) AS SisaJumlah, SUM(NilaiSisa) AS SisaNilai')->toBase();

        $kueri = DB::query()->fromSub(SaldoStok::query()->toBase(), 's')
            ->leftJoinSub($sisa, 'l', fn (JoinClause $j) => $j->on('l.IdProduk', '=', 's.IdProduk')->on('l.IdGudang', '=', 's.IdGudang'))
            ->whereRaw('((s.JumlahTersedia >= 0 AND (COALESCE(l.SisaJumlah, 0) <> s.JumlahTersedia OR COALESCE(l.SisaNilai, 0) <> s.NilaiPersediaan))
                OR (s.JumlahTersedia < 0 AND COALESCE(l.SisaJumlah, 0) <> 0))')
            ->orderBy('s.IdProduk')->orderBy('s.IdGudang')
            ->select(['s.IdProduk', 's.IdGudang', 's.JumlahTersedia', 's.NilaiPersediaan'])
            ->selectRaw('COALESCE(l.SisaJumlah, 0) AS SisaJumlah, COALESCE(l.SisaNilai, 0) AS SisaNilai');

        $hasil = $this->Uraikan($kueri, fn (array $b): string => sprintf(
            'Lapisan FIFO produk %s di lokasi %s: sisa %s / %s, saldo %s / %s.',
            self::Teks($b['IdProduk']), self::Teks($b['IdGudang']), self::Teks($b['SisaJumlah']), self::Teks($b['SisaNilai']),
            self::Teks($b['JumlahTersedia']), self::Teks($b['NilaiPersediaan']),
        ));

        $penanda = LapisanFifo::query()
            ->whereRaw('((Habis = 1 AND JumlahSisa <> 0) OR (Habis = 0 AND JumlahSisa = 0) OR JumlahSisa < 0 OR NilaiSisa < 0)')
            ->orderBy('Id')->select(['Id', 'JumlahSisa', 'NilaiSisa', 'Habis'])->toBase();

        return [...$hasil, ...$this->Uraikan($penanda, fn (array $b): string => sprintf(
            'Lapisan FIFO %s: sisa %s / %s dengan penanda habis %s tidak sesuai.',
            self::Teks($b['Id']), self::Teks($b['JumlahSisa']), self::Teks($b['NilaiSisa']), self::Teks($b['Habis']),
        ))];
    }

    /**
     * `BatchStok.JumlahSisa` = Σ Jumlah mutasi batch itu dan tidak negatif.
     *
     * @return list<string>
     */
    private function PeriksaBatch(): array
    {
        $total = MutasiStok::query()->whereNotNull('IdBatchStok')->groupBy('IdBatchStok')
            ->selectRaw('IdBatchStok, SUM(Jumlah) AS Total')->toBase();

        $kueri = DB::query()->fromSub(BatchStok::query()->toBase(), 'b')
            ->leftJoinSub($total, 'm', 'm.IdBatchStok', '=', 'b.Id')
            ->whereRaw('(b.JumlahSisa <> COALESCE(m.Total, 0) OR b.JumlahSisa < 0)')
            ->orderBy('b.Id')
            ->select(['b.Id', 'b.NomorBatch', 'b.IdProduk', 'b.IdGudang', 'b.JumlahSisa'])
            ->selectRaw('COALESCE(m.Total, 0) AS Total');

        return $this->Uraikan($kueri, fn (array $b): string => sprintf(
            'Batch %s (produk %s, lokasi %s): sisa %s, seharusnya %s.',
            self::Teks($b['NomorBatch']), self::Teks($b['IdProduk']), self::Teks($b['IdGudang']), self::Teks($b['JumlahSisa']), self::Teks($b['Total']),
        ));
    }

    /**
     * Nomor seri Tersedia ⇔ Σ mutasi seri itu = 1, Σ hanya 0 atau 1, dan seri Tersedia berada di lokasi yang
     * saldo serinya 1.
     *
     * @return list<string>
     */
    private function PeriksaNomorSeri(): array
    {
        $total = MutasiStok::query()->whereNotNull('IdNomorSeri')->groupBy('IdNomorSeri')
            ->selectRaw('IdNomorSeri, SUM(Jumlah) AS Total')->toBase();
        $perLokasi = MutasiStok::query()->whereNotNull('IdNomorSeri')->groupBy(['IdNomorSeri', 'IdGudang'])
            ->selectRaw('IdNomorSeri, IdGudang, SUM(Jumlah) AS Total')->toBase();
        $tersedia = StatusNomorSeri::Tersedia->value;

        $kueri = DB::query()->fromSub(NomorSeri::query()->toBase(), 'n')
            ->leftJoinSub($total, 'm', 'm.IdNomorSeri', '=', 'n.Id')
            ->leftJoinSub($perLokasi, 'g', fn (JoinClause $j) => $j->on('g.IdNomorSeri', '=', 'n.Id')->on('g.IdGudang', '=', 'n.IdGudang'))
            ->where(fn (Builder $q) => $q
                ->whereRaw('(n.Status = ?) <> (COALESCE(m.Total, 0) = 1)', [$tersedia])
                ->orWhereRaw('COALESCE(m.Total, 0) NOT IN (0, 1)')
                ->orWhereRaw('(n.Status = ? AND COALESCE(g.Total, 0) <> 1)', [$tersedia]))
            ->orderBy('n.Id')
            ->select(['n.Id', 'n.Nomor', 'n.IdProduk', 'n.Status', 'n.IdGudang'])
            ->selectRaw('COALESCE(m.Total, 0) AS Total');

        return $this->Uraikan($kueri, fn (array $b): string => sprintf(
            'Nomor seri %s (produk %s): status %s di lokasi %s dengan Σ mutasi %s.',
            self::Teks($b['Nomor']), self::Teks($b['IdProduk']), self::Teks($b['Status']), self::Teks($b['IdGudang']), self::Teks($b['Total']),
        ));
    }

    /**
     * @param  callable(array<string, mixed>): string  $format
     * @return list<string>
     */
    private function Uraikan(Builder $kueri, callable $format): array
    {
        $baris = $kueri->limit(self::BATAS_PER_PEMERIKSAAN + 1)->get()->all();
        $hasil = [];

        foreach (array_slice($baris, 0, self::BATAS_PER_PEMERIKSAAN) as $b) {
            $hasil[] = $format((array) $b);
        }

        if (count($baris) > self::BATAS_PER_PEMERIKSAAN) {
            $hasil[] = 'Masih ada perbedaan lain sejenis (hanya '.self::BATAS_PER_PEMERIKSAAN.' pertama yang ditampilkan).';
        }

        return $hasil;
    }

    private static function Teks(mixed $nilai): string
    {
        return is_scalar($nilai) ? (string) $nilai : '-';
    }
}
