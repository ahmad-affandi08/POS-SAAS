<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Data;

use App\Domain\Akuntansi\Enum\JenisTransaksiKasBank;
use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

/**
 * Masukan `SimpanTransaksiKasBank` (F-13a). Akun sumber = sisi kredit, akun tujuan = sisi debit. `idOutlet` = dimensi
 * outlet jurnal (null = tingkat usaha). Lampiran opsional (nota, bukti transfer).
 */
final readonly class DataTransaksiKasBank
{
    public function __construct(
        public JenisTransaksiKasBank $jenis,
        public CarbonImmutable $tanggal,
        public ?int $idOutlet,
        public string $uuidAkunSumber,
        public string $uuidAkunTujuan,
        public Uang $jumlah,
        public string $keterangan,
        public ?UploadedFile $lampiran,
        public ?int $idPengguna,
        public ?int $idJadwalKasBank = null,
    ) {}
}
