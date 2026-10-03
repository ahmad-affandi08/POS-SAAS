<?php

declare(strict_types=1);

namespace App\Domain\Lisensi\Kontrak;

/**
 * D-35: memuat & langsung menerbitkan data master rilis (satuan, wilayah, pajak, katalog fitur, template sektor) di
 * edisi Lisensi. Datanya milik Platform Pengelola, sedangkan `PasangLisensi` tidak boleh memakai domain Pengelola (test
 * arsitektur), jadi pemasangan hanya mengenal kontrak ini; pelaksananya diikat di penyedia layanan.
 */
interface PenyiapDataBawaan
{
    /**
     * @return array{TarifTerbit: int, TemplateTerbit: int, TemplateGagal: list<string>}
     */
    public function Jalankan(): array;
}
