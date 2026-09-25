<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Katalog\Data\DataInfoProdukStok;
use App\Domain\Organisasi\Data\DataInfoGudang;
use App\Domain\Organisasi\Enum\JenisGudang;
use App\Domain\Persediaan\Data\HasilBarisMutasi;
use LogicException;

/**
 * Baris jurnal dokumen persediaan F-05b (J-05.2–J-05.5, §11.3) dari hasil buku stok, bukan dari angka dokumen,
 * sehingga saldo akun persediaan selalu = Σ nilai stok (invarian §23):
 *
 * - sisi persediaan per (peran, outlet lokasi stok) sebesar Σ TotalHpp bertanda: lokasi berjenis DalamPerjalanan →
 *   `PersediaanDalamPerjalanan`, lainnya per jenis produk (`PetaAkunPersediaan`);
 * - sisi lawan per peran yang diminta pemanggil per baris (misal `SusutPersediaan` untuk opname kurang, `SelisihHpp`
 *   untuk opname lebih/selisih BR-04.3) sebesar −TotalHpp baris itu, berdimensi `idOutletLawan`.
 *
 * Jurnal seimbang dengan sendirinya; nilai nol = tanpa baris (daftar kosong = tidak ada jurnal).
 */
final class PenyusunJurnalPersediaan
{
    public function __construct(private readonly PetaAkunPersediaan $petaAkun) {}

    /**
     * @param  list<array{0: HasilBarisMutasi, 1: PeranAkun}>  $baris  hasil mutasi + peran lawan
     * @param  array<int, DataInfoProdukStok>  $produk  kunci = IdProduk
     * @param  array<int, DataInfoGudang>  $gudang  kunci = IdGudang
     * @return list<DataBarisJurnal>
     */
    public function Susun(array $baris, array $produk, array $gudang, ?int $idOutletLawan): array
    {
        /** @var array<string, array{0: PeranAkun, 1: int|null, 2: Uang}> $nilai */
        $nilai = [];

        foreach ($baris as [$hasil, $lawan]) {
            $infoGudang = $gudang[$hasil->idGudang] ?? throw new LogicException("Lokasi stok {$hasil->idGudang} tidak ada di peta jurnal persediaan.");
            $infoProduk = $produk[$hasil->idProduk] ?? throw new LogicException("Produk {$hasil->idProduk} tidak ada di peta jurnal persediaan.");
            $peran = $this->AmbilPeranPersediaan($infoProduk, $infoGudang);
            self::Tambah($nilai, $peran, $infoGudang->idOutlet, $hasil->totalHpp);
            self::Tambah($nilai, $lawan, $idOutletLawan, Uang::Nol()->Kurangi($hasil->totalHpp));
        }

        ksort($nilai);

        return array_values(array_filter(
            array_map(fn (array $n): ?DataBarisJurnal => DataBarisJurnal::DariSelisih($n[0], $n[2], $n[1]), $nilai),
            fn (?DataBarisJurnal $b): bool => $b !== null,
        ));
    }

    public function AmbilPeranPersediaan(DataInfoProdukStok $produk, DataInfoGudang $gudang): PeranAkun
    {
        return $gudang->jenis === JenisGudang::DalamPerjalanan ? PeranAkun::PersediaanDalamPerjalanan : $this->petaAkun->UntukJenis($produk->jenis);
    }

    /**
     * @param  array<string, array{0: PeranAkun, 1: int|null, 2: Uang}>  $nilai
     */
    private static function Tambah(array &$nilai, PeranAkun $peran, ?int $idOutlet, Uang $jumlah): void
    {
        $kunci = $peran->value.'#'.($idOutlet ?? 0);
        $nilai[$kunci] = [$peran, $idOutlet, ($nilai[$kunci][2] ?? Uang::Nol())->Tambah($jumlah)];
    }
}
