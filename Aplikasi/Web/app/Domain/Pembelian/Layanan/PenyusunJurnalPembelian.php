<?php

declare(strict_types=1);

namespace App\Domain\Pembelian\Layanan;

use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Data\DataInfoProdukStok;
use App\Domain\Persediaan\Data\HasilCatatMutasi;
use App\Domain\Persediaan\Layanan\PetaAkunPersediaan;
use LogicException;

/**
 * Baris jurnal pembelian §11.3 (F-04 fase 1), dimensi outlet = outlet dokumen:
 *
 * - Persediaan per peran (`PetaAkunPersediaan`) sebesar Σ TotalHpp buku stok (bertanda: masuk = debit, keluar = kredit);
 * - baris lawan dokumen (GRNI J-04.1, kas/bank J-04.3, hutang J-04.2/J-04.5, PPN masukan);
 * - `SelisihHpp` = penyeimbang: selisih BR-04.3 (penerimaan saat stok minus), selisih harga faktur BR-04.4 (fase 1
 *   seluruhnya ke Selisih HPP), dan selisih nilai retur/pembatalan terhadap HPP berjalan.
 *
 * Jurnal selalu seimbang karena penyeimbangnya dihitung dari Σ debit − Σ kredit baris lain.
 */
final class PenyusunJurnalPembelian
{
    public function __construct(private readonly PetaAkunPersediaan $petaAkun) {}

    /**
     * @param  array<int, DataInfoProdukStok>  $produk  kunci = IdProduk
     * @return list<DataBarisJurnal>
     */
    public function BarisPersediaan(HasilCatatMutasi $hasil, array $produk, ?int $idOutlet): array
    {
        /** @var array<string, Uang> $perPeran */
        $perPeran = [];

        foreach ($hasil->baris as $baris) {
            $info = $produk[$baris->idProduk] ?? throw new LogicException("Produk {$baris->idProduk} tidak ada di peta produk jurnal pembelian.");
            $peran = $this->petaAkun->UntukJenis($info->jenis)->value;
            $perPeran[$peran] = ($perPeran[$peran] ?? Uang::Nol())->Tambah($baris->totalHpp);
        }

        ksort($perPeran);
        $hasilBaris = [];

        foreach ($perPeran as $peran => $nilai) {
            $satu = DataBarisJurnal::DariSelisih(PeranAkun::from($peran), $nilai, $idOutlet);

            if ($satu !== null) {
                $hasilBaris[] = $satu;
            }
        }

        return $hasilBaris;
    }

    /** Baris akun langsung (misal akun kas/bank terpilih) dari nilai bertanda: positif = debit, negatif = kredit. */
    public static function BarisAkun(int $idAkun, Uang $bertanda, ?int $idOutlet, ?string $memo = null): ?DataBarisJurnal
    {
        if ($bertanda->BernilaiNol()) {
            return null;
        }

        return $bertanda->BernilaiNegatif()
            ? new DataBarisJurnal(null, $idAkun, $idOutlet, Uang::Nol(), Uang::Nol()->Kurangi($bertanda), $memo)
            : new DataBarisJurnal(null, $idAkun, $idOutlet, $bertanda, Uang::Nol(), $memo);
    }

    /**
     * Menambah baris `SelisihHpp` penyeimbang (Σ kredit − Σ debit) bila tidak nol.
     *
     * @param  list<DataBarisJurnal|null>  $baris
     * @return list<DataBarisJurnal>
     */
    public static function Seimbangkan(array $baris, ?int $idOutlet): array
    {
        $bersih = array_values(array_filter($baris, fn (?DataBarisJurnal $b): bool => $b !== null));
        $selisih = Uang::Nol();

        foreach ($bersih as $b) {
            $selisih = $selisih->Tambah($b->kredit)->Kurangi($b->debit);
        }

        $penyeimbang = DataBarisJurnal::DariSelisih(PeranAkun::SelisihHpp, $selisih, $idOutlet);

        return $penyeimbang === null ? $bersih : [...$bersih, $penyeimbang];
    }
}
