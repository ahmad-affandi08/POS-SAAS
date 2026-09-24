<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Kasir\Model\KategoriKas;

/**
 * Menonaktifkan/mengaktifkan kategori kas (F-06). Kategori tidak dihapus agar riwayat mutasi tetap terbaca;
 * kategori nonaktif tidak bisa dipakai mutasi baru. Audit `kas.kategori.status`.
 */
final class UbahStatusKategoriKas
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(KategoriKas $kategori, bool $aktif): void
    {
        if ($kategori->Aktif === $aktif) {
            return;
        }

        $kategori->Aktif = $aktif;
        $kategori->save();
        $this->audit->Catat('kas.kategori.status', $kategori, nilaiLama: ['Aktif' => ! $aktif], nilaiBaru: ['Aktif' => $aktif]);
    }
}
