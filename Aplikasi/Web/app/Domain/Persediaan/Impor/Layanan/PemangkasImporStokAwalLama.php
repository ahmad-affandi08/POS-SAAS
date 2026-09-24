<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Layanan;

use App\Domain\Katalog\Impor\Layanan\PenyimpanBerkasImpor;
use App\Domain\Persediaan\Enum\StatusImporStokAwal;
use App\Domain\Persediaan\Model\ImporStokAwal;

/**
 * Retensi impor stok awal (DesainF05a C.7): impor tenant aktif yang lebih tua dari `persediaan.Impor.HariSimpan`
 * hari dihapus beserta berkas privat dan barisnya (baris ikut terhapus lewat FK cascade). Dijalankan malas saat
 * tenant mengunggah (hanya tenant itu, lewat scope `MilikTenant`). Impor yang masih diproses antrean dilewati.
 * Dokumen stok awal hasil impor tidak ikut terhapus: rujukan `StokAwal.IdImporStokAwal` dikosongkan oleh FK
 * `nullOnDelete` (dokumen transaksi tidak pernah dihapus, CLAUDE.md #8).
 */
final class PemangkasImporStokAwalLama
{
    /** Batas per panggilan agar unggahan tetap cepat; sisanya ikut terpangkas pada unggahan berikutnya. */
    private const MAKSIMAL_PER_PANGGILAN = 20;

    public function __construct(private readonly PenyimpanBerkasImpor $penyimpan) {}

    public function Jalankan(): int
    {
        $lama = ImporStokAwal::query()
            ->where('DibuatPada', '<', now()->subDays((int) config('persediaan.Impor.HariSimpan', 30)))
            ->whereNotIn('Status', [StatusImporStokAwal::Memvalidasi->value, StatusImporStokAwal::Menerapkan->value])
            ->orderBy('Id')
            ->limit(self::MAKSIMAL_PER_PANGGILAN)
            ->get();

        foreach ($lama as $impor) {
            $this->penyimpan->Hapus($impor->PathBerkas);
            $impor->delete();
        }

        return $lama->count();
    }
}
