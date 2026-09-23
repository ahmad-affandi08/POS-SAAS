<?php

declare(strict_types=1);

namespace App\Domain\Dukungan\Data;

use App\Domain\Dukungan\Enum\KanalTiketDukungan;
use App\Domain\Dukungan\Enum\KategoriTiketDukungan;
use App\Domain\Dukungan\Enum\PrioritasTiketDukungan;
use Illuminate\Http\UploadedFile;

final readonly class DataTiketBaru
{
    /**
     * @param  list<UploadedFile>  $lampiran
     * @param  array<string, mixed>  $konteks
     */
    public function __construct(
        public KategoriTiketDukungan $kategori,
        public PrioritasTiketDukungan $prioritas,
        public string $judul,
        public string $isi,
        public array $lampiran = [],
        public array $konteks = [],
        public KanalTiketDukungan $kanal = KanalTiketDukungan::BackOffice,
    ) {}
}
