<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Layanan;

use App\Domain\Katalog\Impor\Enum\StatusImporProduk;
use App\Domain\Katalog\Impor\Model\ImporProduk;

/**
 * Retensi impor produk (F-03, DesainF03 C.6 langkah 1): impor tenant aktif yang lebih tua dari
 * `katalog.Impor.HariSimpan` hari dihapus beserta berkas dan barisnya. Dijalankan malas saat tenant mengunggah
 * (hanya tenant itu, lewat scope `MilikTenant`; tidak ada kueri lintas tenant). Impor yang masih berjalan dilewati.
 */
final class PemangkasImporLama
{
    /** Batas per panggilan agar unggahan tetap cepat; sisanya ikut terpangkas pada unggahan berikutnya. */
    private const MAKSIMAL_PER_PANGGILAN = 20;

    public function __construct(private readonly PenyimpanBerkasImpor $penyimpan) {}

    public function Pangkas(): int
    {
        $lama = ImporProduk::query()
            ->where('DibuatPada', '<', now()->subDays((int) config('katalog.Impor.HariSimpan', 30)))
            ->whereNotIn('Status', [StatusImporProduk::Memvalidasi->value, StatusImporProduk::Menerapkan->value])
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
