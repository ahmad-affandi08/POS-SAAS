<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Penjualan\Data\DataMetodeRingkasanShift;
use App\Domain\Penjualan\Data\DataRingkasanPenjualanShift;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Enum\StatusPenjualan;
use App\Domain\Penjualan\Model\IsiDeposit;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PenjualanPembayaran;
use App\Domain\Penjualan\Model\PesananPenjualan;
use App\Domain\Penjualan\Model\PesananPenjualanPembayaran;
use App\Domain\Penjualan\Model\ReturPenjualan;
use App\Domain\Penjualan\Model\VoidPenjualan;

/**
 * Ringkasan penjualan satu shift (F-11: kas seharusnya tutup shift, laporan X/Z, detail shift back-office). API baca
 * publik domain Penjualan untuk domain Kasir (CLAUDE.md #14). Semua penjumlahan di SQL atas kolom DECIMAL (eksak).
 * Dipanggil `TutupShift` di dalam transaksi yang memegang kunci eksklusif baris shift, sehingga penjualan shift itu
 * (yang memegang kunci bersama baris shift, lihat `InfoShift::CariDiPerangkat`) tidak bisa masuk di tengah hitungan.
 *
 * F-09: `refundTunai` = Σ `VoidPenjualan.RefundTunai` shift ini + Σ `ReturPenjualan.RefundTunai` yang dikeluarkan dari
 * laci shift ini; `jumlahRetur`/`nominalRetur` dari retur ber-`IdShift` ini. Penjualan yang di-void tetap dihitung di
 * `tunaiMasukBersih` karena uangnya sempat masuk laci, dan pengembaliannya dikurangkan lewat `refundTunai`. Retur tidak
 * membaca ulang penjualan asal (penjualan shift lain tetap dilaporkan di shift asalnya).
 *
 * F-12 bagian 2: uang muka pre-order yang diterima di shift ini ikut masuk `perMetode` & `tunaiMasukBersih` (uangnya masuk
 * laci/rekening shift ini walau pesanannya kemudian dibatalkan; pengembalian DP dilakukan di back-office dari kas/bank)
 * dan dilaporkan di `jumlahUangMuka`/`nominalUangMuka`. Pemakaian DP saat diambil (metode Uang Muka) bukan uang masuk.
 *
 * F-16d: isi saldo deposit yang diterima di shift ini ikut `perMetode` & `tunaiMasukBersih` (uangnya masuk laci/rekening
 * shift ini walau kemudian dibatalkan; pengembalian lewat back-office dari kas/bank) dan dilaporkan di
 * `jumlahIsiDeposit`/`nominalIsiDeposit`. Pembayaran penjualan dengan metode Deposit bukan uang masuk laci.
 */
final class RingkasanPenjualanShift
{
    /** Status yang dihitung sebagai penjualan pada laporan (void dikeluarkan, dilaporkan terpisah). */
    private const STATUS_PENJUALAN = [StatusPenjualan::Lunas, StatusPenjualan::DireturSebagian, StatusPenjualan::Diretur];

    /** Status yang uang tunainya pernah masuk laci shift (semua penjualan yang diterima). */
    private const STATUS_KAS = [StatusPenjualan::Lunas, StatusPenjualan::DireturSebagian, StatusPenjualan::Diretur, StatusPenjualan::Void];

    public function Ambil(int $idShift): DataRingkasanPenjualanShift
    {
        $total = Penjualan::query()
            ->where('IdShift', $idShift)
            ->whereIn('Status', self::NilaiStatus(self::STATUS_PENJUALAN))
            ->selectRaw('COUNT(*) AS `Jumlah`, SUM(`Subtotal` + `DiskonBaris`) AS `Kotor`, SUM(`TotalDiskon`) AS `Diskon`, SUM(`TotalPajak`) AS `Pajak`, SUM(`BiayaLayanan`) AS `Layanan`, SUM(`Pembulatan`) AS `Pembulatan`, SUM(`TotalAkhir`) AS `Akhir`')
            ->toBase()
            ->first();
        $void = Penjualan::query()
            ->where('IdShift', $idShift)
            ->where('Status', StatusPenjualan::Void->value)
            ->selectRaw('COUNT(*) AS `Jumlah`, SUM(`TotalAkhir`) AS `Akhir`')
            ->toBase()
            ->first();

        $refundVoid = VoidPenjualan::query()->where('IdShift', $idShift)->sum('RefundTunai');
        $retur = ReturPenjualan::query()
            ->where('IdShift', $idShift)
            ->selectRaw('COUNT(*) AS `Jumlah`, SUM(`TotalRefund`) AS `Nominal`, SUM(`RefundTunai`) AS `Tunai`')
            ->toBase()
            ->first();

        $uangMuka = PesananPenjualan::query()
            ->where('IdShift', $idShift)
            ->selectRaw('COUNT(*) AS `Jumlah`, SUM(`UangMuka`) AS `Nominal`')
            ->toBase()
            ->first();

        $isiDeposit = IsiDeposit::query()
            ->where('IdShift', $idShift)
            ->selectRaw('COUNT(*) AS `Jumlah`, SUM(`Jumlah`) AS `Nominal`')
            ->toBase()
            ->first();

        $kotor = self::KeUang($total->Kotor ?? null);
        $diskon = self::KeUang($total->Diskon ?? null);
        $tunaiMasuk = Uang::Nol();

        foreach ($this->AgregatMetode($idShift, self::STATUS_KAS) as $baris) {
            if ($baris->tunai) {
                $tunaiMasuk = $tunaiMasuk->Tambah($baris->jumlah);
            }
        }

        return new DataRingkasanPenjualanShift(
            jumlahTransaksi: (int) ($total->Jumlah ?? 0),
            penjualanKotor: $kotor,
            totalDiskon: $diskon,
            penjualanBersih: $kotor->Kurangi($diskon),
            totalPajak: self::KeUang($total->Pajak ?? null),
            biayaLayanan: self::KeUang($total->Layanan ?? null),
            pembulatan: self::KeUang($total->Pembulatan ?? null),
            totalAkhir: self::KeUang($total->Akhir ?? null),
            perMetode: $this->AgregatMetode($idShift, self::STATUS_PENJUALAN),
            tunaiMasukBersih: $tunaiMasuk,
            // F-09: Σ refund tunai void (shift penjualan = shift void) & retur yang keluar dari laci shift ini.
            refundTunai: self::KeUang($refundVoid)->Tambah(self::KeUang($retur->Tunai ?? null)),
            jumlahVoid: (int) ($void->Jumlah ?? 0),
            nominalVoid: self::KeUang($void->Akhir ?? null),
            // F-09: dokumen retur yang refund-nya dikeluarkan di shift ini (bukan shift penjualan asal).
            jumlahRetur: (int) ($retur->Jumlah ?? 0),
            nominalRetur: self::KeUang($retur->Nominal ?? null),
            jumlahUangMuka: (int) ($uangMuka->Jumlah ?? 0),
            nominalUangMuka: self::KeUang($uangMuka->Nominal ?? null),
            jumlahIsiDeposit: (int) ($isiDeposit->Jumlah ?? 0),
            nominalIsiDeposit: self::KeUang($isiDeposit->Nominal ?? null),
        );
    }

    /**
     * Identitas metode pembayaran tenant aktif per Uuid (validasi hitungan non-tunai tutup shift). Uuid yang tidak
     * dikenal tidak ada di hasil.
     *
     * @param  list<string>  $uuid
     * @return array<string, DataMetodeRingkasanShift> kunci = Uuid; `jumlah` Rp 0
     */
    public function AmbilMetode(array $uuid): array
    {
        $hasil = [];

        if ($uuid === []) {
            return $hasil;
        }

        foreach (MetodePembayaran::query()->whereIn('Uuid', array_values(array_unique($uuid)))->get() as $metode) {
            $hasil[$metode->Uuid] = new DataMetodeRingkasanShift($metode->Uuid, $metode->Jenis->value, $metode->Nama, $metode->Jenis === JenisMetodePembayaran::Tunai, Uang::Nol());
        }

        return $hasil;
    }

    /**
     * Total bersih per metode (tunai dikurangi kembalian penjualannya), urut metode.
     *
     * @param  list<StatusPenjualan>  $status
     * @return list<DataMetodeRingkasanShift>
     */
    private function AgregatMetode(int $idShift, array $status): array
    {
        $baris = PenjualanPembayaran::query()
            ->join('Penjualan', 'Penjualan.Id', '=', 'PenjualanPembayaran.IdPenjualan')
            ->where('Penjualan.IdShift', $idShift)
            ->whereIn('Penjualan.Status', self::NilaiStatus($status))
            ->groupBy('PenjualanPembayaran.IdMetodePembayaran')
            ->selectRaw(
                '`PenjualanPembayaran`.`IdMetodePembayaran` AS `IdMetode`, SUM(`PenjualanPembayaran`.`Jumlah` - CASE WHEN `PenjualanPembayaran`.`JenisMetode` = ? THEN `Penjualan`.`Kembalian` ELSE 0 END) AS `Total`',
                [JenisMetodePembayaran::Tunai->value],
            )
            ->toBase()
            ->get();

        $total = [];

        foreach ($baris as $satu) {
            $total[(int) $satu->IdMetode] = self::KeUang($satu->Total);
        }

        // F-12 bagian 2: uang muka pre-order yang diterima di shift ini (tanpa kembalian).
        $dp = PesananPenjualanPembayaran::query()
            ->join('PesananPenjualan', 'PesananPenjualan.Id', '=', 'PesananPenjualanPembayaran.IdPesananPenjualan')
            ->where('PesananPenjualan.IdShift', $idShift)
            ->groupBy('PesananPenjualanPembayaran.IdMetodePembayaran')
            ->selectRaw('`PesananPenjualanPembayaran`.`IdMetodePembayaran` AS `IdMetode`, SUM(`PesananPenjualanPembayaran`.`Jumlah`) AS `Total`')
            ->toBase()
            ->get();

        foreach ($dp as $satu) {
            $id = (int) $satu->IdMetode;
            $total[$id] = ($total[$id] ?? Uang::Nol())->Tambah(self::KeUang($satu->Total));
        }

        // F-16d: isi saldo deposit yang diterima di shift ini.
        $isi = IsiDeposit::query()
            ->where('IdShift', $idShift)
            ->groupBy('IdMetodePembayaran')
            ->selectRaw('`IdMetodePembayaran` AS `IdMetode`, SUM(`Jumlah`) AS `Total`')
            ->toBase()
            ->get();

        foreach ($isi as $satu) {
            $id = (int) $satu->IdMetode;
            $total[$id] = ($total[$id] ?? Uang::Nol())->Tambah(self::KeUang($satu->Total));
        }

        if ($total === []) {
            return [];
        }

        $hasil = [];

        foreach (MetodePembayaran::query()->whereIn('Id', array_keys($total))->orderBy('Urutan')->orderBy('Id')->get() as $metode) {
            $hasil[] = new DataMetodeRingkasanShift($metode->Uuid, $metode->Jenis->value, $metode->Nama, $metode->Jenis === JenisMetodePembayaran::Tunai, $total[$metode->Id]);
        }

        return $hasil;
    }

    /**
     * @param  list<StatusPenjualan>  $status
     * @return list<string>
     */
    private static function NilaiStatus(array $status): array
    {
        return array_map(fn (StatusPenjualan $s): string => $s->value, $status);
    }

    private static function KeUang(mixed $nilai): Uang
    {
        return Uang::Dari(is_numeric($nilai) ? (string) $nilai : '0');
    }
}
