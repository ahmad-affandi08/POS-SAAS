<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Situs\Layanan\KontenSitusBawaan;
use App\Domain\Situs\Model\HalamanSitus;

/**
 * D-21: menghapus halaman situs buatan konsol (konten pemasaran, bukan dokumen transaksi). Halaman bawaan (beranda,
 * fitur, harga, …) tidak dihapus agar tidak muncul lagi dari isi bawaan; cukup disembunyikan.
 */
final class HapusHalamanSitus
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, HalamanSitus $halaman): void
    {
        if (array_key_exists($halaman->Slug, KontenSitusBawaan::AmbilHalaman())) {
            throw new PelanggaranAturanBisnis('HalamanBawaan', 'Halaman bawaan tidak bisa dihapus. Sembunyikan saja bila tidak dipakai.');
        }

        $this->audit->Catat('situs.halaman.hapus', $halaman, nilaiLama: ['Slug' => $halaman->Slug, 'Judul' => $halaman->Judul], idPelaku: $pelaku->Id);
        $halaman->delete();
    }
}
