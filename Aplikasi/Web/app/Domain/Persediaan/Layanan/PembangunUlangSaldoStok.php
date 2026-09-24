<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Persediaan\Model\BatchStok;
use App\Domain\Persediaan\Model\MutasiStok;
use App\Domain\Persediaan\Model\SaldoStok;
use App\Domain\Tenant\Kueri\PengaturanPersediaanTenant;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Membangun ulang SaldoStok (dan BatchStok.JumlahSisa) tenant aktif dari MutasiStok, per pasangan dalam transaksi sendiri (DesainF05a C.9).
 *
 * `SaldoStok` adalah cache (aturan #9); ledger `MutasiStok` sumber kebenaran dan tidak pernah disentuh di sini.
 * Per pasangan (produk, lokasi stok) urutan kunci mengikuti DesainF05a C.2: L1 Tenant (S) → L3 `SaldoStok FOR
 * UPDATE` → Σ mutasi dengan `sharedLock()` → L4 `BatchStok FOR UPDATE` urut NomorBatch. Yang diperbaiki:
 * `JumlahTersedia` = Σ Jumlah, `NilaiPersediaan` = Σ TotalHpp, `IdMutasiStokTerakhir` = Id mutasi terakhir,
 * `HppRataRata` = `HppRataRataSetelah` mutasi terakhir; baris saldo yang hilang dibuat, baris yatim (tanpa mutasi)
 * dinolkan; `BatchStok.JumlahSisa` = Σ Jumlah mutasi batch itu. Lapisan FIFO dan status nomor seri hanya
 * dilaporkan `PemeriksaKonsistensiStok` (H-18). Satu `LogAudit` `persediaan.saldo.bangun-ulang` per tenant bila
 * ada yang diperbaiki.
 */
final class PembangunUlangSaldoStok
{
    /** Batas rincian pasangan yang disimpan di LogAudit (sisanya hanya dihitung). */
    private const BATAS_RINCIAN_AUDIT = 100;

    public function __construct(
        private readonly PengaturanPersediaanTenant $pengaturan,
        private readonly PencatatAudit $audit,
        private readonly KonteksTenant $konteks,
    ) {}

    /**
     * @return int jumlah pasangan (produk, lokasi stok) yang diperbaiki
     */
    public function Jalankan(): int
    {
        $pasangan = $this->AmbilSemuaPasangan();
        $rincian = [];
        $diperbaiki = 0;

        foreach ($pasangan as [$idProduk, $idGudang]) {
            $perubahan = $this->BangunUlangPasangan($idProduk, $idGudang);

            if ($perubahan === null) {
                continue;
            }

            $diperbaiki++;

            if (count($rincian) < self::BATAS_RINCIAN_AUDIT) {
                $rincian[] = $perubahan;
            }
        }

        if ($diperbaiki > 0) {
            $this->audit->Catat('persediaan.saldo.bangun-ulang', nilaiBaru: [
                'JumlahPasangan' => count($pasangan),
                'JumlahDiperbaiki' => $diperbaiki,
                'Rincian' => $rincian,
            ]);
        }

        return $diperbaiki;
    }

    /**
     * Semua pasangan (IdProduk, IdGudang) tenant aktif yang punya saldo, mutasi, atau batch; urut naik.
     *
     * @return list<array{int, int}>
     */
    private function AmbilSemuaPasangan(): array
    {
        $kueri = SaldoStok::query()->select(['IdProduk', 'IdGudang'])->toBase()
            ->union(MutasiStok::query()->select(['IdProduk', 'IdGudang'])->distinct()->toBase())
            ->union(BatchStok::query()->select(['IdProduk', 'IdGudang'])->distinct()->toBase());

        $hasil = [];

        foreach (DB::query()->fromSub($kueri, 'Pasangan')->orderBy('IdProduk')->orderBy('IdGudang')->get() as $baris) {
            $data = (array) $baris;
            $hasil[] = [self::KeInt($data['IdProduk'] ?? 0), self::KeInt($data['IdGudang'] ?? 0)];
        }

        return $hasil;
    }

    /**
     * Satu pasangan dalam transaksinya sendiri. Mengembalikan rincian perubahan, atau null bila sudah benar.
     *
     * @return array<string, mixed>|null
     */
    private function BangunUlangPasangan(int $idProduk, int $idGudang): ?array
    {
        $percobaan = config('persediaan.PercobaanTransaksi', 3);

        return DB::transaction(function () use ($idProduk, $idGudang): ?array {
            $this->pengaturan->AmbilDenganKunciBaca();
            $saldo = $this->KunciSaldo($idProduk, $idGudang);

            $ringkasan = MutasiStok::query()
                ->where('IdProduk', $idProduk)
                ->where('IdGudang', $idGudang)
                ->sharedLock()
                ->selectRaw('COUNT(*) AS Banyak, COALESCE(SUM(Jumlah), 0) AS TotalJumlah, COALESCE(SUM(TotalHpp), 0) AS TotalNilai, MAX(Id) AS IdTerakhir')
                ->toBase()
                ->first();
            $ringkasan = (array) $ringkasan;
            $idTerakhir = isset($ringkasan['IdTerakhir']) ? self::KeInt($ringkasan['IdTerakhir']) : null;
            $hppTerakhir = $idTerakhir === null ? null : MutasiStok::query()->whereKey($idTerakhir)->value('HppRataRataSetelah');

            $harapan = [
                'JumlahTersedia' => BigDecimal::of(self::KeTeks($ringkasan['TotalJumlah'] ?? '0'))->toScale(4),
                'NilaiPersediaan' => BigDecimal::of(self::KeTeks($ringkasan['TotalNilai'] ?? '0'))->toScale(2),
                'HppRataRata' => $hppTerakhir === null ? null : BigDecimal::of(self::KeTeks($hppTerakhir))->toScale(6),
            ];

            $perubahan = [];

            if ($saldo === null) {
                $saldo = $this->BuatSaldo($idProduk, $idGudang);
                $perubahan['BarisDibuat'] = true;
            }

            foreach ($harapan as $kolom => $nilai) {
                $lama = $saldo->getAttribute($kolom);

                if (! self::SamaDesimal(is_string($lama) ? $lama : null, $nilai)) {
                    $perubahan[$kolom] = ['Lama' => is_string($lama) ? $lama : null, 'Baru' => $nilai === null ? null : (string) $nilai];
                    $saldo->setAttribute($kolom, $nilai === null ? null : (string) $nilai);
                }
            }

            if ($saldo->IdMutasiStokTerakhir !== $idTerakhir) {
                $perubahan['IdMutasiStokTerakhir'] = ['Lama' => $saldo->IdMutasiStokTerakhir, 'Baru' => $idTerakhir];
                $saldo->IdMutasiStokTerakhir = $idTerakhir;
            }

            if ($saldo->isDirty()) {
                $saldo->save();
            }

            $batch = $this->BangunUlangBatch($idProduk, $idGudang);

            if ($batch !== []) {
                $perubahan['Batch'] = $batch;
            }

            return $perubahan === [] ? null : ['IdProduk' => $idProduk, 'IdGudang' => $idGudang, ...$perubahan];
        }, is_int($percobaan) ? $percobaan : 3);
    }

    private function KunciSaldo(int $idProduk, int $idGudang): ?SaldoStok
    {
        return SaldoStok::query()->where('IdProduk', $idProduk)->where('IdGudang', $idGudang)->lockForUpdate()->first();
    }

    /**
     * Baris saldo yang hilang dibuat lewat upsert (ODKU mengambil kunci X langsung, aman bila mesin buku stok
     * membuatnya bersamaan), lalu dibaca ulang dengan `FOR UPDATE`.
     */
    private function BuatSaldo(int $idProduk, int $idGudang): SaldoStok
    {
        SaldoStok::query()->upsert([[
            'IdTenant' => $this->konteks->Wajib(),
            'IdProduk' => $idProduk,
            'IdGudang' => $idGudang,
            'JumlahTersedia' => '0.0000',
            'JumlahDipesan' => '0.0000',
            'NilaiPersediaan' => '0.00',
        ]], ['IdTenant', 'IdProduk', 'IdGudang'], ['IdProduk']);

        return $this->KunciSaldo($idProduk, $idGudang) ?? throw new LogicException('Saldo stok gagal dibuat');
    }

    /**
     * `BatchStok.JumlahSisa` = Σ Jumlah mutasi batch itu (L4, urut NomorBatch).
     *
     * @return list<array{IdBatchStok: int, NomorBatch: string, Lama: string, Baru: string}>
     */
    private function BangunUlangBatch(int $idProduk, int $idGudang): array
    {
        $daftar = BatchStok::query()->where('IdProduk', $idProduk)->where('IdGudang', $idGudang)
            ->orderBy('NomorBatch')->lockForUpdate()->get();

        if ($daftar->isEmpty()) {
            return [];
        }

        $total = [];

        foreach (MutasiStok::query()->whereIn('IdBatchStok', $daftar->modelKeys())->sharedLock()
            ->groupBy('IdBatchStok')->selectRaw('IdBatchStok, SUM(Jumlah) AS Total')->toBase()->get() as $baris) {
            $data = (array) $baris;
            $total[self::KeInt($data['IdBatchStok'] ?? 0)] = self::KeTeks($data['Total'] ?? '0');
        }

        $perubahan = [];

        foreach ($daftar as $batch) {
            $harapan = BigDecimal::of($total[$batch->Id] ?? '0')->toScale(4);

            if (! self::SamaDesimal($batch->JumlahSisa, $harapan)) {
                $perubahan[] = ['IdBatchStok' => $batch->Id, 'NomorBatch' => $batch->NomorBatch, 'Lama' => $batch->JumlahSisa, 'Baru' => (string) $harapan];
                $batch->JumlahSisa = (string) $harapan;
                $batch->save();
            }
        }

        return $perubahan;
    }

    private static function SamaDesimal(?string $lama, ?BigDecimal $baru): bool
    {
        if ($lama === null || $baru === null) {
            return $lama === null && $baru === null;
        }

        return BigDecimal::of($lama)->isEqualTo($baru);
    }

    private static function KeInt(mixed $nilai): int
    {
        return is_numeric($nilai) ? (int) $nilai : 0;
    }

    private static function KeTeks(mixed $nilai): string
    {
        return is_scalar($nilai) ? (string) $nilai : '0';
    }
}
