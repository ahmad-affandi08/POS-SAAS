<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Persediaan\Model\BatchStok;
use Carbon\CarbonImmutable;

/**
 * Alokasi **FEFO** (First Expired, First Out; F-05g): penjualan produk ber-batch mengambil batch berkedaluwarsa
 * terdekat lebih dulu (tanpa kedaluwarsa paling akhir, seri menurut nomor batch lalu Id).
 *
 * Murni **perencana**: hanya membaca `BatchStok`, tidak mengunci dan tidak menulis. Yang mengunci dan menjamin batch
 * tidak pernah minus tetap mesin buku stok (`CatatMutasiStok`, `StokBatchTidakCukup`). Pemanggil yang memakai hasil
 * rencana ini di dalam transaksinya harus siap rencananya basi karena penjualan lain mengambil batch yang sama di
 * antara pembacaan dan penguncian, lalu merencanakan ulang.
 *
 * Beberapa permintaan (baris keranjang) untuk batch yang sama dihitung berurutan dengan sisa yang terus berkurang,
 * sehingga dua baris produk yang sama tidak mengambil unit yang sama dua kali.
 */
final class AlokatorBatchFefo
{
    /**
     * `$bacaTerbaru` membaca data terkini (`LOCK IN SHARE MODE`) alih-alih snapshot transaksi; dipakai hanya saat merencanakan ulang
     * setelah `StokBatchTidakCukup`, karena snapshot MySQL (REPEATABLE READ) tidak melihat penjualan lain yang sudah commit.
     *
     * @param  list<array{Kunci: string, IdProduk: int, IdGudang: int, Jumlah: Kuantitas}>  $permintaan  `Jumlah` positif (besaran keluar), urutan = prioritas
     * @return array{Alokasi: array<string, list<array{IdBatchStok: int, NomorBatch: string, TanggalKedaluwarsa: CarbonImmutable|null, Jumlah: Kuantitas}>>, Sisa: array<string, Kuantitas>} `Sisa` = bagian yang tidak tertutup batch mana pun (hanya kunci yang sisanya > 0)
     */
    public function Susun(array $permintaan, bool $bacaTerbaru = false): array
    {
        if ($permintaan === []) {
            return ['Alokasi' => [], 'Sisa' => []];
        }

        $idProduk = array_values(array_unique(array_map(fn (array $p): int => $p['IdProduk'], $permintaan)));
        $idGudang = array_values(array_unique(array_map(fn (array $p): int => $p['IdGudang'], $permintaan)));

        /** @var array<string, list<BatchStok>> $perPasangan */
        $perPasangan = [];
        $sisaBatch = [];

        $kueri = BatchStok::query();

        if ($bacaTerbaru) {
            $kueri->sharedLock();
        }

        foreach ($kueri
            ->whereIn('IdProduk', $idProduk)
            ->whereIn('IdGudang', $idGudang)
            ->where('JumlahSisa', '>', 0)
            ->orderByRaw('`TanggalKedaluwarsa` IS NULL')
            ->orderBy('TanggalKedaluwarsa')
            ->orderBy('NomorBatch')
            ->orderBy('Id')
            ->get() as $b) {
            $perPasangan[$b->IdProduk.':'.$b->IdGudang][] = $b;
            $sisaBatch[$b->Id] = Kuantitas::Dari($b->JumlahSisa);
        }

        $alokasi = [];
        $sisa = [];

        foreach ($permintaan as $p) {
            $butuh = $p['Jumlah'];
            $alokasi[$p['Kunci']] = [];

            foreach ($perPasangan[$p['IdProduk'].':'.$p['IdGudang']] ?? [] as $b) {
                if ($butuh->BernilaiNol() || $butuh->BernilaiNegatif()) {
                    break;
                }

                $tersedia = $sisaBatch[$b->Id];

                if ($tersedia->BernilaiNol() || $tersedia->BernilaiNegatif()) {
                    continue;
                }

                $ambil = $tersedia->Bandingkan($butuh) < 0 ? $tersedia : $butuh;
                $alokasi[$p['Kunci']][] = [
                    'IdBatchStok' => $b->Id,
                    'NomorBatch' => $b->NomorBatch,
                    'TanggalKedaluwarsa' => $b->TanggalKedaluwarsa === null ? null : CarbonImmutable::parse($b->TanggalKedaluwarsa->toDateString()),
                    'Jumlah' => $ambil,
                ];
                $sisaBatch[$b->Id] = $tersedia->Kurangi($ambil);
                $butuh = $butuh->Kurangi($ambil);
            }

            if (! $butuh->BernilaiNol() && ! $butuh->BernilaiNegatif()) {
                $sisa[$p['Kunci']] = $butuh;
            }
        }

        return ['Alokasi' => $alokasi, 'Sisa' => $sisa];
    }
}
