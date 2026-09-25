<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Layanan;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Data\HasilPostingJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use Carbon\CarbonImmutable;

/**
 * Memposting jurnal otomatis dokumen persediaan F-05b lewat inti jurnal (`PostingJurnal`, idempoten per sumber &
 * kunci), di transaksi dokumen pemanggil (aturan #10). Baris kosong (nilai nol) = tidak ada jurnal (null).
 */
final class PencatatJurnalPersediaan
{
    public function __construct(private readonly PostingJurnal $postingJurnal) {}

    /**
     * @param  list<DataBarisJurnal>  $baris
     */
    public function Posting(
        JenisSumberJurnal $jenis,
        int $idSumber,
        string $uuidSumber,
        string $nomorSumber,
        CarbonImmutable $tanggal,
        string $keterangan,
        array $baris,
        int $idPengguna,
        string $kunciSumber = 'Utama',
    ): ?HasilPostingJurnal {
        if ($baris === []) {
            return null;
        }

        return $this->postingJurnal->Jalankan(new DataJurnal(
            jenisSumber: $jenis,
            idSumber: $idSumber,
            uuidSumber: $uuidSumber,
            nomorSumber: $nomorSumber,
            tanggal: $tanggal,
            keterangan: mb_substr($keterangan, 0, 255),
            baris: $baris,
            idPengguna: $idPengguna,
            kunciSumber: $kunciSumber,
        ));
    }
}
