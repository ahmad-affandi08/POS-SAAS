<?php

declare(strict_types=1);

namespace Tests\Pendukung\Persediaan;

use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Pemeriksa invarian stok & jurnal F-05a (aturan #9, #17, DesainF05a F) dengan SQL mentah per tenant, sengaja
 * TIDAK memakai kode aplikasi (model, scope, value object) supaya bug di mesin buku stok tidak ikut "membenarkan"
 * dirinya sendiri. Semua perbandingan desimal dilakukan di MySQL (DECIMAL, eksak). Setiap metode mengembalikan
 * daftar uraian pelanggaran; daftar kosong = invarian terpenuhi.
 *
 *     expect(PemeriksaInvarian::PeriksaSemua($idTenant))->toBe([]);
 */
final class PemeriksaInvarian
{
    /**
     * Semua invarian. `fifo` = tenant ber-MetodeHpp FIFO (memeriksa lapisan terhadap saldo).
     *
     * @return list<string>
     */
    public static function PeriksaSemua(int $idTenant, bool $fifo = false): array
    {
        return [
            ...self::PeriksaSaldoStok($idTenant),
            ...self::PeriksaRantaiMutasi($idTenant),
            ...self::PeriksaNilaiNolSaatJumlahNol($idTenant),
            ...($fifo ? self::PeriksaLapisanFifo($idTenant) : []),
            ...self::PeriksaBatch($idTenant),
            ...self::PeriksaNomorSeri($idTenant),
            ...self::PeriksaJurnalSeimbang($idTenant),
            ...self::PeriksaAkunPersediaan($idTenant),
        ];
    }

    /**
     * `SaldoStok.JumlahTersedia = Σ MutasiStok.Jumlah` dan `NilaiPersediaan = Σ TotalHpp` per (produk, lokasi),
     * termasuk pasangan yang punya mutasi tanpa baris saldo (dan sebaliknya saldo bukan nol tanpa mutasi).
     *
     * @return list<string>
     */
    public static function PeriksaSaldoStok(int $idTenant): array
    {
        $baris = self::Pilih(
            'SELECT p.IdProduk, p.IdGudang, s.JumlahTersedia, s.NilaiPersediaan, m.TotalJumlah, m.TotalNilai
               FROM (SELECT IdProduk, IdGudang FROM SaldoStok WHERE IdTenant = ?
                     UNION SELECT IdProduk, IdGudang FROM MutasiStok WHERE IdTenant = ?) p
               LEFT JOIN SaldoStok s ON s.IdTenant = ? AND s.IdProduk = p.IdProduk AND s.IdGudang = p.IdGudang
               LEFT JOIN (SELECT IdProduk, IdGudang, SUM(Jumlah) AS TotalJumlah, SUM(TotalHpp) AS TotalNilai
                            FROM MutasiStok WHERE IdTenant = ? GROUP BY IdProduk, IdGudang) m
                      ON m.IdProduk = p.IdProduk AND m.IdGudang = p.IdGudang
              WHERE COALESCE(s.JumlahTersedia, 0) <> COALESCE(m.TotalJumlah, 0)
                 OR COALESCE(s.NilaiPersediaan, 0) <> COALESCE(m.TotalNilai, 0)
              ORDER BY p.IdProduk, p.IdGudang',
            [$idTenant, $idTenant, $idTenant, $idTenant],
        );

        return array_map(fn (stdClass $b): string => sprintf(
            'SaldoStok produk %s gudang %s: jumlah %s ≠ Σ mutasi %s atau nilai %s ≠ Σ TotalHpp %s',
            self::Teks($b, 'IdProduk'), self::Teks($b, 'IdGudang'), self::Teks($b, 'JumlahTersedia'),
            self::Teks($b, 'TotalJumlah'), self::Teks($b, 'NilaiPersediaan'), self::Teks($b, 'TotalNilai'),
        ), $baris);
    }

    /**
     * Rantai `SaldoSetelah`/`NilaiSetelah` tiap baris mutasi = jumlah berjalan urut Id per (produk, lokasi).
     *
     * @return list<string>
     */
    public static function PeriksaRantaiMutasi(int $idTenant): array
    {
        $baris = self::Pilih(
            'SELECT Id, IdProduk, IdGudang, SaldoSetelah, NilaiSetelah, Berjalan, NilaiBerjalan
               FROM (SELECT Id, IdProduk, IdGudang, SaldoSetelah, NilaiSetelah,
                            SUM(Jumlah) OVER (PARTITION BY IdProduk, IdGudang ORDER BY Id) AS Berjalan,
                            SUM(TotalHpp) OVER (PARTITION BY IdProduk, IdGudang ORDER BY Id) AS NilaiBerjalan
                       FROM MutasiStok WHERE IdTenant = ?) r
              WHERE SaldoSetelah <> Berjalan OR NilaiSetelah <> NilaiBerjalan
              ORDER BY Id',
            [$idTenant],
        );

        return array_map(fn (stdClass $b): string => sprintf(
            'MutasiStok %s (produk %s gudang %s): SaldoSetelah %s ≠ berjalan %s atau NilaiSetelah %s ≠ berjalan %s',
            self::Teks($b, 'Id'), self::Teks($b, 'IdProduk'), self::Teks($b, 'IdGudang'), self::Teks($b, 'SaldoSetelah'),
            self::Teks($b, 'Berjalan'), self::Teks($b, 'NilaiSetelah'), self::Teks($b, 'NilaiBerjalan'),
        ), $baris);
    }

    /**
     * Q = 0 ⇒ N = 0 dan Q > 0 ⇒ N ≥ 0 (DesainF05a C.3).
     *
     * @return list<string>
     */
    public static function PeriksaNilaiNolSaatJumlahNol(int $idTenant): array
    {
        $baris = self::Pilih(
            'SELECT IdProduk, IdGudang, JumlahTersedia, NilaiPersediaan FROM SaldoStok
              WHERE IdTenant = ? AND ((JumlahTersedia = 0 AND NilaiPersediaan <> 0) OR (JumlahTersedia > 0 AND NilaiPersediaan < 0))
              ORDER BY IdProduk, IdGudang',
            [$idTenant],
        );

        return array_map(fn (stdClass $b): string => sprintf(
            'SaldoStok produk %s gudang %s: jumlah %s dengan nilai %s',
            self::Teks($b, 'IdProduk'), self::Teks($b, 'IdGudang'), self::Teks($b, 'JumlahTersedia'), self::Teks($b, 'NilaiPersediaan'),
        ), $baris);
    }

    /**
     * FIFO: saat Q ≥ 0, Σ JumlahSisa/NilaiSisa lapisan terbuka = saldo; saat Q ≤ 0 tidak ada lapisan bersisa.
     * `Habis` = (JumlahSisa = 0).
     *
     * @return list<string>
     */
    public static function PeriksaLapisanFifo(int $idTenant): array
    {
        $baris = self::Pilih(
            'SELECT s.IdProduk, s.IdGudang, s.JumlahTersedia, s.NilaiPersediaan,
                    COALESCE(l.SisaJumlah, 0) AS SisaJumlah, COALESCE(l.SisaNilai, 0) AS SisaNilai
               FROM SaldoStok s
               LEFT JOIN (SELECT IdProduk, IdGudang, SUM(JumlahSisa) AS SisaJumlah, SUM(NilaiSisa) AS SisaNilai
                            FROM LapisanFifo WHERE IdTenant = ? AND Habis = 0 GROUP BY IdProduk, IdGudang) l
                      ON l.IdProduk = s.IdProduk AND l.IdGudang = s.IdGudang
              WHERE s.IdTenant = ?
                AND ((s.JumlahTersedia >= 0 AND (COALESCE(l.SisaJumlah, 0) <> s.JumlahTersedia OR COALESCE(l.SisaNilai, 0) <> s.NilaiPersediaan))
                  OR (s.JumlahTersedia < 0 AND COALESCE(l.SisaJumlah, 0) <> 0))
              ORDER BY s.IdProduk, s.IdGudang',
            [$idTenant, $idTenant],
        );

        $salah = array_map(fn (stdClass $b): string => sprintf(
            'LapisanFifo produk %s gudang %s: sisa %s/%s ≠ saldo %s/%s',
            self::Teks($b, 'IdProduk'), self::Teks($b, 'IdGudang'), self::Teks($b, 'SisaJumlah'), self::Teks($b, 'SisaNilai'),
            self::Teks($b, 'JumlahTersedia'), self::Teks($b, 'NilaiPersediaan'),
        ), $baris);

        $habis = self::Pilih(
            'SELECT Id FROM LapisanFifo WHERE IdTenant = ? AND ((Habis = 1 AND JumlahSisa <> 0) OR (Habis = 0 AND JumlahSisa = 0) OR JumlahSisa < 0) ORDER BY Id',
            [$idTenant],
        );

        return [...$salah, ...array_map(fn (stdClass $b): string => 'LapisanFifo '.self::Teks($b, 'Id').': penanda Habis tidak sesuai JumlahSisa', $habis)];
    }

    /**
     * `BatchStok.JumlahSisa = Σ MutasiStok.Jumlah` baris batch itu, dan tidak pernah negatif.
     *
     * @return list<string>
     */
    public static function PeriksaBatch(int $idTenant): array
    {
        $baris = self::Pilih(
            'SELECT b.Id, b.JumlahSisa, COALESCE(m.Total, 0) AS Total
               FROM BatchStok b
               LEFT JOIN (SELECT IdBatchStok, SUM(Jumlah) AS Total FROM MutasiStok
                           WHERE IdTenant = ? AND IdBatchStok IS NOT NULL GROUP BY IdBatchStok) m ON m.IdBatchStok = b.Id
              WHERE b.IdTenant = ? AND (b.JumlahSisa <> COALESCE(m.Total, 0) OR b.JumlahSisa < 0)
              ORDER BY b.Id',
            [$idTenant, $idTenant],
        );

        return array_map(fn (stdClass $b): string => sprintf(
            'BatchStok %s: JumlahSisa %s ≠ Σ mutasi %s (atau negatif)', self::Teks($b, 'Id'), self::Teks($b, 'JumlahSisa'), self::Teks($b, 'Total'),
        ), $baris);
    }

    /**
     * Nomor seri berstatus Tersedia ⇔ Σ mutasi seri itu = 1 (dan lokasinya = lokasi mutasi bersaldo).
     *
     * @return list<string>
     */
    public static function PeriksaNomorSeri(int $idTenant): array
    {
        $baris = self::Pilih(
            "SELECT n.Id, n.Status, COALESCE(m.Total, 0) AS Total
               FROM NomorSeri n
               LEFT JOIN (SELECT IdNomorSeri, SUM(Jumlah) AS Total FROM MutasiStok
                           WHERE IdTenant = ? AND IdNomorSeri IS NOT NULL GROUP BY IdNomorSeri) m ON m.IdNomorSeri = n.Id
              WHERE n.IdTenant = ? AND ((n.Status = 'Tersedia') <> (COALESCE(m.Total, 0) = 1) OR COALESCE(m.Total, 0) NOT IN (0, 1))
              ORDER BY n.Id",
            [$idTenant, $idTenant],
        );

        return array_map(fn (stdClass $b): string => sprintf(
            'NomorSeri %s: status %s dengan Σ mutasi %s', self::Teks($b, 'Id'), self::Teks($b, 'Status'), self::Teks($b, 'Total'),
        ), $baris);
    }

    /**
     * Setiap jurnal: Σ Debit = Σ Kredit = TotalDebit = TotalKredit, tiap baris tepat satu sisi > 0 dan tidak negatif;
     * seluruh tenant: Σ Debit = Σ Kredit.
     *
     * @return list<string>
     */
    public static function PeriksaJurnalSeimbang(int $idTenant): array
    {
        $jurnal = self::Pilih(
            'SELECT j.Id, j.Nomor, j.TotalDebit, j.TotalKredit, COALESCE(d.Debit, 0) AS Debit, COALESCE(d.Kredit, 0) AS Kredit
               FROM Jurnal j
               LEFT JOIN (SELECT IdJurnal, SUM(Debit) AS Debit, SUM(Kredit) AS Kredit FROM JurnalDetail
                           WHERE IdTenant = ? GROUP BY IdJurnal) d ON d.IdJurnal = j.Id
              WHERE j.IdTenant = ?
                AND (COALESCE(d.Debit, 0) <> COALESCE(d.Kredit, 0) OR j.TotalDebit <> COALESCE(d.Debit, 0) OR j.TotalKredit <> COALESCE(d.Kredit, 0))
              ORDER BY j.Id',
            [$idTenant, $idTenant],
        );

        $hasil = array_map(fn (stdClass $b): string => sprintf(
            'Jurnal %s: Σ debit %s, Σ kredit %s, TotalDebit %s, TotalKredit %s',
            self::Teks($b, 'Nomor'), self::Teks($b, 'Debit'), self::Teks($b, 'Kredit'), self::Teks($b, 'TotalDebit'), self::Teks($b, 'TotalKredit'),
        ), $jurnal);

        $barisSalah = self::Pilih(
            'SELECT Id FROM JurnalDetail WHERE IdTenant = ? AND (Debit < 0 OR Kredit < 0 OR (Debit > 0) = (Kredit > 0)) ORDER BY Id',
            [$idTenant],
        );

        foreach ($barisSalah as $b) {
            $hasil[] = 'JurnalDetail '.self::Teks($b, 'Id').': harus tepat satu sisi > 0 dan tidak negatif';
        }

        $total = self::Pilih('SELECT COALESCE(SUM(Debit), 0) AS Debit, COALESCE(SUM(Kredit), 0) AS Kredit, COALESCE(SUM(Debit), 0) = COALESCE(SUM(Kredit), 0) AS Seimbang FROM JurnalDetail WHERE IdTenant = ?', [$idTenant]);

        if ($total !== [] && (int) self::Teks($total[0], 'Seimbang') !== 1) {
            $hasil[] = sprintf('Tenant %d: Σ debit %s ≠ Σ kredit %s', $idTenant, self::Teks($total[0], 'Debit'), self::Teks($total[0], 'Kredit'));
        }

        return $hasil;
    }

    /**
     * Saldo akun persediaan (Debit − Kredit pada akun yang dipetakan ke PersediaanBarangDagang/PersediaanBahanBaku, dan
     * sejak F-05b PersediaanDalamPerjalanan untuk stok di lokasi dalam perjalanan transfer; tingkat tenant maupun
     * outlet) = Σ `SaldoStok.NilaiPersediaan`.
     *
     * @return list<string>
     */
    public static function PeriksaAkunPersediaan(int $idTenant): array
    {
        $hasil = self::Pilih(
            "SELECT
                (SELECT COALESCE(SUM(d.Debit - d.Kredit), 0) FROM JurnalDetail d
                  WHERE d.IdTenant = ? AND d.IdAkun IN (SELECT p.IdAkun FROM PemetaanAkun p
                                                         WHERE p.IdTenant = ? AND p.Kunci IN ('PersediaanBarangDagang', 'PersediaanBahanBaku', 'PersediaanDalamPerjalanan'))) AS SaldoAkun,
                (SELECT COALESCE(SUM(s.NilaiPersediaan), 0) FROM SaldoStok s WHERE s.IdTenant = ?) AS NilaiStok",
            [$idTenant, $idTenant, $idTenant],
        );

        if ($hasil === []) {
            return [];
        }

        $sama = self::Pilih('SELECT CAST(? AS DECIMAL(20,2)) = CAST(? AS DECIMAL(20,2)) AS Sama', [self::Teks($hasil[0], 'SaldoAkun'), self::Teks($hasil[0], 'NilaiStok')]);

        return $sama !== [] && (int) self::Teks($sama[0], 'Sama') === 1 ? [] : [sprintf(
            'Tenant %d: saldo akun persediaan %s ≠ Σ nilai persediaan %s', $idTenant, self::Teks($hasil[0], 'SaldoAkun'), self::Teks($hasil[0], 'NilaiStok'),
        )];
    }

    /**
     * @param  list<int|string>  $ikatan
     * @return list<stdClass>
     */
    private static function Pilih(string $sql, array $ikatan): array
    {
        $baris = [];

        foreach (DB::select($sql, $ikatan) as $b) {
            if ($b instanceof stdClass) {
                $baris[] = $b;
            }
        }

        return $baris;
    }

    private static function Teks(stdClass $baris, string $kolom): string
    {
        $nilai = $baris->{$kolom} ?? null;

        return is_scalar($nilai) ? (string) $nilai : 'NULL';
    }
}
