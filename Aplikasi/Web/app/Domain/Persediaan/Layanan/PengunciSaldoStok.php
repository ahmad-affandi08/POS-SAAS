<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Persediaan\Model\SaldoStok;

/**
 * Mengunci baris SaldoStok (kunci L3, DesainF05a C.2) urut (IdProduk, IdGudang):
 * 1. upsert baris nol untuk semua pasangan dalam satu pernyataan terurut. `INSERT … ON DUPLICATE KEY UPDATE`
 *    langsung mengambil kunci X baris yang sudah ada maupun yang baru (tanpa naik S→X yang rawan deadlock), dan
 *    tidak mengubah isi baris yang sudah ada;
 * 2. membacanya kembali dengan `FOR UPDATE` urut (IdProduk, IdGudang).
 *
 * Publik dan reentran (memanggil ulang di transaksi yang sama aman). Wajib dipanggil di dalam transaksi pemanggil
 * (di luar transaksi kunci langsung lepas).
 */
final class PengunciSaldoStok
{
    private const UKURAN_POTONGAN = 500;

    public function __construct(private readonly KonteksTenant $konteks) {}

    /**
     * @param  list<array{int, int}>  $pasangan  (IdProduk, IdGudang)
     * @return array<string, SaldoStok> kunci = SaldoStok::BuatKunciPasangan()
     */
    public function Kunci(array $pasangan): array
    {
        $urut = self::UrutkanUnik($pasangan);

        if ($urut === []) {
            return [];
        }

        $idTenant = $this->konteks->Wajib();
        $sekarang = now();
        $hasil = [];

        foreach (array_chunk($urut, self::UKURAN_POTONGAN) as $potongan) {
            SaldoStok::query()->toBase()->upsert(
                array_map(fn (array $p): array => [
                    'IdTenant' => $idTenant,
                    'IdProduk' => $p[0],
                    'IdGudang' => $p[1],
                    'JumlahTersedia' => '0.0000',
                    'JumlahDipesan' => '0.0000',
                    'NilaiPersediaan' => '0.00',
                    'DibuatPada' => $sekarang,
                    'DiubahPada' => $sekarang,
                ], $potongan),
                ['IdTenant', 'IdProduk', 'IdGudang'],
                ['IdTenant'],
            );
        }

        foreach (array_chunk($urut, self::UKURAN_POTONGAN) as $potongan) {
            $saldo = SaldoStok::query()
                ->whereRaw(self::BuatKlausaPasangan(count($potongan)), array_merge(...$potongan))
                ->orderBy('IdProduk')
                ->orderBy('IdGudang')
                ->lockForUpdate()
                ->get();

            foreach ($saldo as $baris) {
                $hasil[SaldoStok::BuatKunciPasangan($baris->IdProduk, $baris->IdGudang)] = $baris;
            }
        }

        return $hasil;
    }

    /**
     * Pasangan unik urut (IdProduk, IdGudang) naik: urutan kunci global L3.
     *
     * @param  list<array{int, int}>  $pasangan
     * @return list<array{int, int}>
     */
    public static function UrutkanUnik(array $pasangan): array
    {
        $unik = [];

        foreach ($pasangan as [$idProduk, $idGudang]) {
            $unik[SaldoStok::BuatKunciPasangan($idProduk, $idGudang)] = [$idProduk, $idGudang];
        }

        $hasil = array_values($unik);
        usort($hasil, fn (array $a, array $b): int => $a <=> $b);

        return $hasil;
    }

    /**
     * `(IdProduk, IdGudang) IN ((?, ?), …)` untuk `jumlah` pasangan (hanya placeholder, nilai lewat ikatan).
     *
     * @return literal-string
     */
    public static function BuatKlausaPasangan(int $jumlah): string
    {
        return '(IdProduk, IdGudang) IN ('.implode(', ', array_fill(0, max(1, $jumlah), '(?, ?)')).')';
    }
}
