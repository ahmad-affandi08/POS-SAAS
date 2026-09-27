<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Data\DataInfoProdukStok;
use App\Domain\Persediaan\Data\HasilBarisMutasi;

/**
 * Jurnal J-05.6 order produksi (§11.3) dari hasil buku stok, sehingga saldo akun persediaan = Σ nilai stok:
 *
 * - Dr persediaan hasil (peran per jenis produk, `PetaAkunPersediaan`) sebesar TotalHpp mutasi hasil;
 * - Cr persediaan bahan per peran sebesar Σ |TotalHpp| mutasi bahan;
 * - Cr `OverheadProduksiDibebankan` sebesar biaya overhead;
 * - selisih HPP mutasi hasil (misal hasil sebelumnya minus, BR-04.3) ke `SelisihHpp`.
 *
 * Pembatalan memakai hasil mutasi pembalik dengan overhead bertanda kebalikan. Seimbang dengan sendirinya; nilai nol
 * = tanpa baris.
 */
final class PenyusunJurnalProduksi
{
    public function __construct(private readonly PetaAkunPersediaan $petaAkun) {}

    /**
     * @param  list<HasilBarisMutasi>  $bahan
     * @param  array<int, DataInfoProdukStok>  $produk  kunci = IdProduk
     * @return list<DataBarisJurnal>
     */
    public function Susun(HasilBarisMutasi $hasil, array $bahan, array $produk, Uang $overheadBertanda, ?int $idOutlet): array
    {
        /** @var array<string, array{0: PeranAkun, 1: Uang}> $nilai */
        $nilai = [];
        $tambah = function (PeranAkun $peran, Uang $jumlah) use (&$nilai): void {
            $nilai[$peran->value] = [$peran, ($nilai[$peran->value][1] ?? Uang::Nol())->Tambah($jumlah)];
        };

        $tambah($this->petaAkun->UntukJenis($produk[$hasil->idProduk]->jenis), $hasil->totalHpp);
        // TotalHpp = NilaiDiminta + SelisihHpp; sisi lawan selisih ke Selisih HPP.
        $tambah(PeranAkun::SelisihHpp, Uang::Nol()->Kurangi($hasil->selisihHpp));

        foreach ($bahan as $b) {
            $tambah($this->petaAkun->UntukJenis($produk[$b->idProduk]->jenis), $b->totalHpp);
        }

        $tambah(PeranAkun::OverheadProduksiDibebankan, Uang::Nol()->Kurangi($overheadBertanda));
        ksort($nilai);

        return array_values(array_filter(
            array_map(fn (array $n): ?DataBarisJurnal => DataBarisJurnal::DariSelisih($n[0], $n[1], $idOutlet), $nilai),
            fn (?DataBarisJurnal $b): bool => $b !== null,
        ));
    }
}
