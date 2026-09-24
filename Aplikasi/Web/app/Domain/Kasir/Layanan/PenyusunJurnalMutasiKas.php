<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Layanan;

use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Kasir\Enum\JenisMutasiKas;
use App\Domain\Kasir\Model\KategoriKas;
use App\Domain\Kasir\Model\MutasiKas;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * Jurnal mutasi kas (PRD §11.3, v1.34), semua dengan dimensi outlet shift:
 * - Keluar (J-06.1): Dr akun kategori / Cr Kas Outlet.
 * - Masuk: Dr Kas Outlet / Cr akun kategori.
 * - Setoran (J-11.3): Dr Kas Brankas / Cr Kas Outlet.
 */
final class PenyusunJurnalMutasiKas
{
    public function Susun(MutasiKas $mutasi, ?KategoriKas $kategori, int $idOutlet, string $uuidShift): DataJurnal
    {
        $nilai = Uang::Dari($mutasi->Jumlah);
        $memo = $mutasi->Catatan;

        $baris = match ($mutasi->Jenis) {
            JenisMutasiKas::Keluar => [
                new DataBarisJurnal(null, $this->AmbilIdAkun($kategori), $idOutlet, $nilai, Uang::Nol(), $memo),
                DataBarisJurnal::Kredit(PeranAkun::KasOutlet, $nilai, $idOutlet),
            ],
            JenisMutasiKas::Masuk => [
                DataBarisJurnal::Debit(PeranAkun::KasOutlet, $nilai, $idOutlet),
                new DataBarisJurnal(null, $this->AmbilIdAkun($kategori), $idOutlet, Uang::Nol(), $nilai, $memo),
            ],
            JenisMutasiKas::Setoran => [
                DataBarisJurnal::Debit(PeranAkun::KasBrankas, $nilai, $idOutlet, $memo),
                DataBarisJurnal::Kredit(PeranAkun::KasOutlet, $nilai, $idOutlet),
            ],
        };

        $judul = $mutasi->Jenis->AmbilLabel().($kategori !== null ? ": {$kategori->Nama}" : '');

        return new DataJurnal(
            jenisSumber: JenisSumberJurnal::MutasiKas,
            idSumber: $mutasi->Id,
            uuidSumber: $mutasi->Uuid,
            nomorSumber: null,
            tanggal: CarbonImmutable::parse($mutasi->TanggalBisnis->toDateString()),
            keterangan: mb_substr("{$judul} (shift {$uuidShift})", 0, 255),
            baris: $baris,
            idPengguna: $mutasi->DicatatOleh,
        );
    }

    private function AmbilIdAkun(?KategoriKas $kategori): int
    {
        if ($kategori === null) {
            throw new LogicException('Mutasi kas masuk/keluar wajib berkategori.');
        }

        return $kategori->IdAkun;
    }
}
