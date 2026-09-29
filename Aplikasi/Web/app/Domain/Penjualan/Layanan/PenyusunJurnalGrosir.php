<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Bersama\Nilai\Uang;

/**
 * Baris jurnal dokumen grosir (PRD §11.3 J-12.1 & J-12.3).
 *
 * **J-12.1 surat jalan** — penyerahan barang, bukan penagihan:
 * - Dr `PiutangBelumDifakturkan` sebesar total penyerahan (termasuk PPN yang sudah terutang);
 * - Dr `DiskonPenjualan` sebesar diskon baris, Cr `Penjualan` sebesar bruto − pajak inklusif (persis pola J-07.1,
 *   supaya laporan penjualan grosir & ritel bisa dibandingkan apa adanya);
 * - Cr `PpnKeluaran` / `HutangPbjt` per kode jenis pajak;
 * - Dr `Hpp`, Cr persediaan per peran akun dari hasil mutasi stok (J-07.2).
 *
 * Seimbang tanpa bergantung pembulatan: Total + Diskon = Subtotal + PajakEksklusif, dan Pendapatan = DPP + Diskon
 * = Subtotal − PajakInklusif, sehingga sisi debit dan kredit sama-sama Subtotal + PajakEksklusif.
 *
 * **J-12.3 pembatalan** = seluruh baris J-12.1 dengan sisi terbalik (`pembalik: true`); perubahan persediaan tidak
 * dibalik di sini karena hasil mutasi pembalik sudah bertanda benar (stok masuk kembali).
 *
 * Alasan `PiutangBelumDifakturkan` dipakai sebagai lawan, bukan langsung `PiutangUsaha`: PPN keluaran terutang saat
 * penyerahan (UU PPN Pasal 11 ayat 1, PP 44/2022) sedangkan piutang yang bisa ditagih baru ada setelah faktur
 * diterbitkan (UU PPN Pasal 13 ayat 2 mengizinkan satu faktur gabungan per bulan kalender). Tanpa akun perantara ini,
 * umur piutang akan menghitung tagihan yang belum pernah dikirim ke pembeli.
 */
final class PenyusunJurnalGrosir
{
    public function __construct(private readonly PenyusunJurnalPenjualan $penyusunPenjualan) {}

    /**
     * @param  array<string, Uang>  $pajak  kode jenis pajak → jumlah pajak dokumen
     * @param  array<string, Uang>  $perubahanPersediaan  nilai `PeranAkun` persediaan → Σ TotalHpp mutasi (bertanda)
     * @return list<DataBarisJurnal>
     */
    public function BarisSuratJalan(
        Uang $total,
        Uang $diskon,
        Uang $pendapatan,
        array $pajak,
        array $perubahanPersediaan,
        ?int $idOutlet,
        bool $pembalik = false,
    ): array {
        $arah = fn (Uang $nilai): Uang => $pembalik ? Uang::Nol()->Kurangi($nilai) : $nilai;
        $baris = [
            DataBarisJurnal::DariSelisih(PeranAkun::PiutangBelumDifakturkan, $arah($total), $idOutlet),
            DataBarisJurnal::DariSelisih(PeranAkun::DiskonPenjualan, $arah($diskon), $idOutlet),
            DataBarisJurnal::DariSelisih(PeranAkun::Penjualan, $arah(Uang::Nol()->Kurangi($pendapatan)), $idOutlet),
        ];
        $akunPajak = $this->penyusunPenjualan->TentukanAkunPajak(array_map('strval', array_keys($pajak)));

        foreach ($pajak as $kode => $jumlah) {
            $baris[] = DataBarisJurnal::DariSelisih($akunPajak[(string) $kode], $arah(Uang::Nol()->Kurangi($jumlah)), $idOutlet);
        }

        $totalPerubahan = Uang::Nol();

        foreach ($perubahanPersediaan as $peran => $perubahan) {
            $baris[] = DataBarisJurnal::DariSelisih(PeranAkun::from($peran), $perubahan, $idOutlet);
            $totalPerubahan = $totalPerubahan->Tambah($perubahan);
        }

        $baris[] = DataBarisJurnal::DariSelisih(PeranAkun::Hpp, Uang::Nol()->Kurangi($totalPerubahan), $idOutlet);

        return array_values(array_filter($baris, fn (?DataBarisJurnal $b): bool => $b !== null));
    }
}
