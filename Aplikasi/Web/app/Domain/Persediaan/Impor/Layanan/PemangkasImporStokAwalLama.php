<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Layanan;

use App\Domain\Katalog\Impor\Layanan\PenyimpanBerkasImpor;
use App\Domain\Persediaan\Enum\StatusImporStokAwal;
use App\Domain\Persediaan\Model\ImporStokAwal;
use App\Domain\Persediaan\Model\ImporStokAwalBaris;
use App\Domain\Persediaan\Model\StokAwal;
use Illuminate\Support\Facades\DB;

/**
 * Retensi impor stok awal (DesainF05a C.7): impor tenant aktif yang lebih tua dari `persediaan.Impor.HariSimpan`
 * hari dipangkas. Dijalankan malas saat tenant mengunggah (hanya tenant itu, lewat scope `MilikTenant`). Impor yang
 * masih diproses antrean dilewati.
 * - Berkas privat dan baris impor selalu dihapus.
 * - Impor yang masih dirujuk dokumen stok awal (`StokAwal.IdImporStokAwal`, status apa pun) **tidak dihapus**: asal
 *   dokumen transaksi tidak boleh hilang (CLAUDE.md #8; FK `nullOnDelete` akan mengosongkan rujukannya). Barisnya
 *   dihapus dan `PathBerkas` dikosongkan (penanda "sudah dipangkas", tidak diproses lagi).
 * - Impor tanpa dokumen dihapus seluruhnya.
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
            ->where('PathBerkas', '!=', '')
            ->orderBy('Id')
            ->limit(self::MAKSIMAL_PER_PANGGILAN)
            ->get();

        if ($lama->isEmpty()) {
            return 0;
        }

        $dirujuk = array_flip(array_map('intval', StokAwal::query()
            ->whereIn('IdImporStokAwal', $lama->pluck('Id')->all())
            ->distinct()
            ->pluck('IdImporStokAwal')
            ->all()));

        foreach ($lama as $impor) {
            $this->penyimpan->Hapus($impor->PathBerkas);

            DB::transaction(function () use ($impor, $dirujuk): void {
                if (! isset($dirujuk[$impor->Id])) {
                    $impor->delete();

                    return;
                }

                ImporStokAwalBaris::query()->where('IdImporStokAwal', $impor->Id)->delete();
                $impor->PathBerkas = '';
                $impor->save();
            });
        }

        return $lama->count();
    }
}
