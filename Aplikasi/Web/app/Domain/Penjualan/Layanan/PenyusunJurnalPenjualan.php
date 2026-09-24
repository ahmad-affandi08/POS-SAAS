<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Penjualan\Model\Penjualan;
use Carbon\CarbonImmutable;

/**
 * Jurnal penjualan satu dokumen (PRD §11.3 J-07.1 & J-07.2, "Rincian F-07b"), dimensi outlet penjualan:
 * - Dr per pembayaran: tunai → akun metode atau Kas Outlet (dikurangi kembalian); QRIS statis/EDC/e-wallet → akun
 *   kliring metode atau Piutang Pencairan; transfer → akun metode atau Bank.
 * - Dr Diskon Penjualan (total diskon baris + pesanan).
 * - Cr Penjualan / Pendapatan Jasa per baris = bruto − pajak inklusif baris.
 * - Cr Pendapatan Biaya Layanan; Cr pajak (Ppn → PPN Keluaran, lainnya → Hutang PB1/PBJT).
 * - Pembulatan tunai ke Pendapatan Lain (kredit bila positif, debit bila negatif).
 * - J-07.2: Dr HPP / Cr persediaan per peran akun persediaan (dari hasil mutasi stok).
 * Seimbang karena TotalAkhir + TotalDiskon = Σ Bruto + BiayaLayanan + PajakEksklusif + Pembulatan.
 */
final class PenyusunJurnalPenjualan
{
    public const KODE_PPN = 'Ppn';

    /**
     * @param  list<array{0: PeranAkun, 1: Uang}>  $pendapatan  per baris: [Penjualan|PendapatanJasa, bruto − pajak inklusif]
     * @param  array<string, Uang>  $pajak  kode jenis pajak → jumlah pajak dokumen
     * @param  list<array{0: MetodePembayaran, 1: Uang}>  $pembayaran  [metode, nilai bersih (tunai sudah dikurangi kembalian)]
     * @param  array<string, Uang>  $perubahanPersediaan  nilai PeranAkun persediaan → Σ TotalHpp mutasi (bertanda, keluar negatif)
     */
    public function Susun(Penjualan $penjualan, array $pendapatan, array $pajak, array $pembayaran, array $perubahanPersediaan): DataJurnal
    {
        $idOutlet = $penjualan->IdOutlet;
        $baris = [];

        foreach ($pembayaran as [$metode, $nilai]) {
            $baris[] = self::BarisPembayaran($metode, $nilai, $idOutlet);
        }

        $baris[] = DataBarisJurnal::Debit(PeranAkun::DiskonPenjualan, Uang::Dari($penjualan->TotalDiskon), $idOutlet);

        foreach ($pendapatan as [$peran, $nilai]) {
            $baris[] = DataBarisJurnal::DariSelisih($peran, Uang::Nol()->Kurangi($nilai), $idOutlet);
        }

        $baris[] = DataBarisJurnal::Kredit(PeranAkun::PendapatanBiayaLayanan, Uang::Dari($penjualan->BiayaLayanan), $idOutlet);

        foreach ($pajak as $kode => $jumlah) {
            $baris[] = DataBarisJurnal::Kredit($kode === self::KODE_PPN ? PeranAkun::PpnKeluaran : PeranAkun::HutangPbjt, $jumlah, $idOutlet);
        }

        $baris[] = DataBarisJurnal::DariSelisih(PeranAkun::PendapatanLain, Uang::Nol()->Kurangi(Uang::Dari($penjualan->Pembulatan)), $idOutlet);

        $totalPerubahan = Uang::Nol();

        foreach ($perubahanPersediaan as $peran => $perubahan) {
            $baris[] = DataBarisJurnal::DariSelisih(PeranAkun::from($peran), $perubahan, $idOutlet);
            $totalPerubahan = $totalPerubahan->Tambah($perubahan);
        }

        $baris[] = DataBarisJurnal::DariSelisih(PeranAkun::Hpp, Uang::Nol()->Kurangi($totalPerubahan), $idOutlet);

        return new DataJurnal(
            jenisSumber: JenisSumberJurnal::Penjualan,
            idSumber: $penjualan->Id,
            uuidSumber: $penjualan->Uuid,
            nomorSumber: $penjualan->Nomor,
            tanggal: CarbonImmutable::parse($penjualan->TanggalBisnis->toDateString()),
            keterangan: mb_substr("Penjualan {$penjualan->Nomor}", 0, 255),
            baris: array_values(array_filter($baris, fn (?DataBarisJurnal $b): bool => $b !== null)),
            idPengguna: $penjualan->IdPengguna,
        );
    }

    private static function BarisPembayaran(MetodePembayaran $metode, Uang $nilai, int $idOutlet): DataBarisJurnal
    {
        [$idAkun, $peran] = match ($metode->Jenis) {
            JenisMetodePembayaran::Tunai => [$metode->IdAkun, PeranAkun::KasOutlet],
            JenisMetodePembayaran::Transfer => [$metode->IdAkun, PeranAkun::Bank],
            default => [$metode->IdAkunKliring, PeranAkun::PiutangPencairan],
        };

        return $idAkun !== null
            ? new DataBarisJurnal(null, $idAkun, $idOutlet, $nilai, Uang::Nol(), $metode->Nama)
            : DataBarisJurnal::Debit($peran, $nilai, $idOutlet, $metode->Nama);
    }
}
