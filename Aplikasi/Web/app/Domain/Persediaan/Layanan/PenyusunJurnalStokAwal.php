<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Data\DataInfoProdukStok;
use App\Domain\Organisasi\Data\DataInfoGudang;
use App\Domain\Persediaan\Data\HasilCatatMutasi;
use LogicException;

/**
 * Baris jurnal J-05.1 stok awal (DesainF05a C.6.4 langkah 8, C.6.5 langkah 5), dimensi outlet = outlet lokasi stok:
 *
 * - Persediaan per peran (`PetaAkunPersediaan`) sebesar Σ TotalHpp (bertanda: + debit saat posting, − kredit saat
 *   pembatalan);
 * - `EkuitasSaldoAwal` sebesar −Σ NilaiDiminta (kredit saat posting, debit saat pembatalan);
 * - `SelisihHpp` sebesar −Σ SelisihHpp (BR-04.3 stok awal setelah stok minus, H-16).
 *
 * Karena SelisihHpp = TotalHpp − NilaiDiminta per baris, jurnal seimbang dengan sendirinya. Semua nilai nol = daftar
 * kosong (pemanggil tidak memposting jurnal).
 */
final class PenyusunJurnalStokAwal
{
    public function __construct(private readonly PetaAkunPersediaan $petaAkun) {}

    /**
     * @param  array<int, DataInfoProdukStok>  $produk  kunci = IdProduk
     * @return list<DataBarisJurnal>
     */
    public function Susun(HasilCatatMutasi $hasil, array $produk, DataInfoGudang $gudang): array
    {
        /** @var array<string, Uang> $persediaan kunci = PeranAkun::value */
        $persediaan = [];

        foreach ($hasil->baris as $baris) {
            $info = $produk[$baris->idProduk] ?? throw new LogicException("Produk {$baris->idProduk} tidak ada di peta produk jurnal stok awal.");
            $peran = $this->petaAkun->UntukJenis($info->jenis)->value;
            $persediaan[$peran] = ($persediaan[$peran] ?? Uang::Nol())->Tambah($baris->totalHpp);
        }

        ksort($persediaan);
        $hasilBaris = [];

        foreach ($persediaan as $peran => $nilai) {
            $hasilBaris[] = DataBarisJurnal::DariSelisih(PeranAkun::from($peran), $nilai, $gudang->idOutlet);
        }

        $hasilBaris[] = DataBarisJurnal::DariSelisih(PeranAkun::EkuitasSaldoAwal, Uang::Nol()->Kurangi($hasil->TotalNilaiDiminta()), $gudang->idOutlet);
        $hasilBaris[] = DataBarisJurnal::DariSelisih(PeranAkun::SelisihHpp, Uang::Nol()->Kurangi($hasil->TotalSelisih()), $gudang->idOutlet);

        return array_values(array_filter($hasilBaris, fn (?DataBarisJurnal $b): bool => $b !== null));
    }
}
