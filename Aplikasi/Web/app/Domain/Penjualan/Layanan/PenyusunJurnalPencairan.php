<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Bersama\Nilai\Uang;

/**
 * Baris jurnal pencairan dana non-tunai (PRD §11.3 **J-08.1**, F-08 BR-08.4).
 *
 * Yang dilunasi adalah sisi debit yang dulu dibuat J-07.1: setiap pembayaran QRIS/kartu/gerbang/ojol mendebet akun
 * kliring metodenya (atau peran `PiutangPencairan` bila metodenya tidak punya akun kliring sendiri). Pencairan
 * mengkreditnya kembali **sebesar nilai transaksinya** (`JumlahKotor`), dan memecah uang yang benar-benar masuk:
 *
 * - Dr akun kas/bank tujuan sebesar `JumlahBersih`;
 * - Dr `BebanBiayaPembayaran` sebesar potongan platform (MDR, komisi ojol) bila ada;
 * - Cr akun kliring sebesar `JumlahKotor`.
 *
 * **Kelebihan setor masuk `PendapatanLain`, bukan beban bernilai negatif.** Bila platform menyetor lebih besar daripada
 * nilai transaksinya (pembalikan refund, subsidi promo yang ikut dibayarkan), mengkreditkan selisihnya ke
 * `BebanBiayaPembayaran` akan membuat laba-rugi membaca seolah biaya pembayaran sedang turun — padahal yang terjadi
 * adalah uang masuk yang belum dijelaskan. Menempatkannya di pendapatan lain membuatnya terlihat dan bisa ditanyakan.
 *
 * Seimbang tanpa bergantung pembulatan, karena `Biaya` didefinisikan `JumlahKotor − JumlahBersih`: bila positif,
 * `Bersih + Biaya = Kotor`; bila negatif, `Bersih = Kotor + |Biaya|`.
 */
final class PenyusunJurnalPencairan
{
    /**
     * `idAkunKliring` null = metode tanpa akun kliring sendiri, jadi yang dikredit peran `PiutangPencairan` — persis
     * peran yang didebet J-07.1, termasuk pemetaan per outletnya.
     *
     * @param  Uang  $biaya  bertanda: positif = potongan platform, negatif = kelebihan setor
     * @return list<DataBarisJurnal>
     */
    public function Baris(
        Uang $jumlahKotor,
        Uang $jumlahBersih,
        Uang $biaya,
        int $idAkunTujuan,
        ?int $idAkunKliring,
        ?int $idOutlet,
        bool $pembalik = false,
    ): array {
        $arah = fn (Uang $nilai): Uang => $pembalik ? Uang::Nol()->Kurangi($nilai) : $nilai;
        $baris = [
            self::DariSelisihAkun($idAkunTujuan, $arah($jumlahBersih), $idOutlet),
            DataBarisJurnal::DariSelisih(PeranAkun::BebanBiayaPembayaran, $arah($biaya->BernilaiNegatif() ? Uang::Nol() : $biaya), $idOutlet),
            DataBarisJurnal::DariSelisih(PeranAkun::PendapatanLain, $arah($biaya->BernilaiNegatif() ? $biaya : Uang::Nol()), $idOutlet),
            $idAkunKliring === null
                ? DataBarisJurnal::DariSelisih(PeranAkun::PiutangPencairan, $arah(Uang::Nol()->Kurangi($jumlahKotor)), $idOutlet)
                : self::DariSelisihAkun($idAkunKliring, $arah(Uang::Nol()->Kurangi($jumlahKotor)), $idOutlet),
        ];

        return array_values(array_filter($baris, fn (?DataBarisJurnal $b): bool => $b !== null));
    }

    /** Cermin `DataBarisJurnal::DariSelisih` untuk akun yang sudah diketahui Id-nya (bukan peran). */
    private static function DariSelisihAkun(int $idAkun, Uang $bertanda, ?int $idOutlet): ?DataBarisJurnal
    {
        if ($bertanda->BernilaiNol()) {
            return null;
        }

        return $bertanda->BernilaiNegatif()
            ? new DataBarisJurnal(null, $idAkun, $idOutlet, Uang::Nol(), Uang::Nol()->Kurangi($bertanda))
            : new DataBarisJurnal(null, $idAkun, $idOutlet, $bertanda, Uang::Nol());
    }
}
