<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Penjualan\Model\ReturPenjualan;
use Carbon\CarbonImmutable;

/**
 * Jurnal retur penjualan satu dokumen (PRD §11.3 J-09.2, "Rincian F-09 fase 1"), dimensi outlet retur:
 * - Dr Retur Penjualan = Σ nilai retur − pajak − biaya layanan (pendapatan bersih diskon yang dibalik).
 * - Dr pajak bagian retur per kode (Ppn → PPN Keluaran, lainnya → Hutang PB1/PBJT); Dr Pendapatan Biaya Layanan.
 * - Cr per refund: tunai → akun metode atau Kas Outlet; transfer → akun metode atau Bank.
 * - Dr persediaan per peran akun (nilai stok yang kembali) / Cr HPP.
 * Seimbang karena Σ refund = Σ nilai retur (diperiksa `TerimaReturPenjualanPos`).
 */
final class PenyusunJurnalReturPenjualan
{
    /**
     * @param  array<string, Uang>  $pajak  kode jenis pajak → pajak bagian retur
     * @param  list<array{0: MetodePembayaran, 1: Uang}>  $refund
     * @param  array<string, Uang>  $persediaan  nilai PeranAkun persediaan → nilai stok yang kembali (positif)
     */
    public function Susun(ReturPenjualan $retur, Uang $totalNilai, Uang $biayaLayanan, array $pajak, array $refund, array $persediaan): DataJurnal
    {
        $idOutlet = $retur->IdOutlet;
        $totalPajak = array_reduce($pajak, fn (Uang $t, Uang $u): Uang => $t->Tambah($u), Uang::Nol());
        $baris = [
            DataBarisJurnal::DariSelisih(PeranAkun::ReturPenjualan, $totalNilai->Kurangi($totalPajak)->Kurangi($biayaLayanan), $idOutlet),
            DataBarisJurnal::DariSelisih(PeranAkun::PendapatanBiayaLayanan, $biayaLayanan, $idOutlet),
        ];

        foreach ($pajak as $kode => $jumlah) {
            $baris[] = DataBarisJurnal::DariSelisih($kode === PenyusunJurnalPenjualan::KODE_PPN ? PeranAkun::PpnKeluaran : PeranAkun::HutangPbjt, $jumlah, $idOutlet);
        }

        foreach ($refund as [$metode, $nilai]) {
            [$idAkun, $peran] = PenyusunJurnalPenjualan::TentukanAkunMetode($metode);
            $baris[] = $idAkun !== null
                ? new DataBarisJurnal(null, $idAkun, $idOutlet, Uang::Nol(), $nilai, $metode->Nama)
                : DataBarisJurnal::Kredit($peran, $nilai, $idOutlet, $metode->Nama);
        }

        $totalPersediaan = Uang::Nol();

        foreach ($persediaan as $peran => $nilai) {
            $baris[] = DataBarisJurnal::DariSelisih(PeranAkun::from($peran), $nilai, $idOutlet);
            $totalPersediaan = $totalPersediaan->Tambah($nilai);
        }

        $baris[] = DataBarisJurnal::DariSelisih(PeranAkun::Hpp, Uang::Nol()->Kurangi($totalPersediaan), $idOutlet);

        return new DataJurnal(
            jenisSumber: JenisSumberJurnal::ReturPenjualan,
            idSumber: $retur->Id,
            uuidSumber: $retur->Uuid,
            nomorSumber: $retur->Nomor,
            tanggal: CarbonImmutable::parse($retur->TanggalBisnis->toDateString()),
            keterangan: mb_substr("Retur penjualan {$retur->Nomor}", 0, 255),
            baris: array_values(array_filter($baris, fn (?DataBarisJurnal $b): bool => $b !== null)),
            idPengguna: $retur->IdPengguna,
        );
    }
}
