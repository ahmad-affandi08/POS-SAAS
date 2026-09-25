<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Data;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use Carbon\CarbonImmutable;

/**
 * Masukan `PostingJurnal` (DesainF05a C.5). Idempoten per (jenisSumber, idSumber, kunciSumber); `kunciSumber`
 * membedakan beberapa jurnal satu dokumen (misal `Utama` dan `Pembatalan`). `otomatis = false` = jurnal manual
 * (ditulis ke LogAudit `jurnal.posting`).
 */
final readonly class DataJurnal
{
    /**
     * @param  list<DataBarisJurnal>  $baris
     */
    public function __construct(
        public JenisSumberJurnal $jenisSumber,
        public int $idSumber,
        public ?string $uuidSumber,
        public ?string $nomorSumber,
        public CarbonImmutable $tanggal,
        public string $keterangan,
        public array $baris,
        public ?int $idPengguna,
        public string $kunciSumber = 'Utama',
        public bool $otomatis = true,
        public ?int $idJurnalDibalik = null,
    ) {}

    /** Salinan dengan tanggal posting lain dan keterangan tambahan (F-15: periode asal terkunci). */
    public function DenganTanggal(CarbonImmutable $tanggal, string $tambahanKeterangan = ''): self
    {
        return new self($this->jenisSumber, $this->idSumber, $this->uuidSumber, $this->nomorSumber, $tanggal, $this->keterangan.$tambahanKeterangan, $this->baris, $this->idPengguna, $this->kunciSumber, $this->otomatis, $this->idJurnalDibalik);
    }
}
