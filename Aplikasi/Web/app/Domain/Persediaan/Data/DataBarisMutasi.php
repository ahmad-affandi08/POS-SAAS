<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Data;

use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Persediaan\Enum\JenisMutasi;
use App\Domain\Persediaan\Enum\ModeNilaiMutasi;
use Brick\Math\BigDecimal;

/**
 * Satu baris masukan `CatatMutasiStok` (DesainF05a C.2).
 *
 * - `kunciBaris`: unik di dalam dokumen, ≤ 80 karakter (kunci idempotensi bersama JenisReferensi + IdReferensi).
 * - `jumlah`: bertanda (+ masuk, − keluar) dalam satuan dasar, ≠ 0.
 * - `nilai`: wajib untuk `Ditentukan`, besaran ≥ 0.
 * - `hppSatuan`: opsional, skala ≤ 6 (disimpan sebagai `MutasiStok.HppSatuan` / biaya lapisan).
 * - Batch: `batchMasuk` untuk masuk, `idBatchStok` untuk keluar. Seri: `nomorSeriMasuk` untuk masuk (|jumlah| = 1),
 *   `idNomorSeri` untuk keluar.
 * - `idMutasiAsal`: baris ini membalik baris tertentu (jenis sama, tanda kebalikan).
 */
final readonly class DataBarisMutasi
{
    public function __construct(
        public string $kunciBaris,
        public int $idProduk,
        public int $idGudang,
        public JenisMutasi $jenisMutasi,
        public Kuantitas $jumlah,
        public ModeNilaiMutasi $modeNilai,
        public ?Uang $nilai = null,
        public ?BigDecimal $hppSatuan = null,
        public ?int $idReferensiDetail = null,
        public ?DataBatchMasuk $batchMasuk = null,
        public ?int $idBatchStok = null,
        public ?string $nomorSeriMasuk = null,
        public ?int $idNomorSeri = null,
        public ?int $idMutasiAsal = null,
    ) {}
}
