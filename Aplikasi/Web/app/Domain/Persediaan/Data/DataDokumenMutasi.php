<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Data;

use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use Carbon\CarbonImmutable;

/**
 * Masukan `CatatMutasiStok` (DesainF05a C.2): satu dokumen sumber beserta baris mutasinya. Diproses dalam urutan
 * baris; idempoten per (jenisReferensi, idReferensi, kunciBaris).
 *
 * `abaikanBatasMinus` (F-07b, §18.3): transaksi yang sudah terjadi di perangkat (penjualan offline) tidak boleh ditolak
 * karena stok tidak cukup. Bila true, pemeriksaan BR-05.2 dilewati untuk dokumen ini dan baris yang sebenarnya
 * melanggar ditandai `HasilBarisMutasi::stokTidakCukup`. **Hanya untuk produk tanpa pelacakan batch/seri**: dokumen
 * ber-`abaikanBatasMinus` yang memuat produk berpelacakan ditolak `PelacakanBelumDidukung` (stok batch/seri tidak
 * pernah boleh minus, BR-05.2), jadi pemanggil wajib menolak produk berpelacakan lebih dulu. Bawaan false.
 */
final readonly class DataDokumenMutasi
{
    /**
     * @param  list<DataBarisMutasi>  $baris
     */
    public function __construct(
        public JenisReferensiMutasi $jenisReferensi,
        public int $idReferensi,
        public ?string $uuidReferensi,
        public ?string $nomorReferensi,
        public CarbonImmutable $tanggalBisnis,
        public ?int $idPengguna,
        public ?int $idPerangkat,
        public array $baris,
        public bool $abaikanBatasMinus = false,
    ) {}
}
