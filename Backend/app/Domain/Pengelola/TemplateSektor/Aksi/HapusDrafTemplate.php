<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TemplateSektor\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\PanduanAwal\Enum\StatusTemplateSektor;
use App\Domain\PanduanAwal\Model\TemplateSektor;
use App\Domain\PanduanAwal\Model\TemplateSektorVersi;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Support\Facades\DB;

/**
 * Menghapus draf template (BR-P03.2, BR-P03.4). Versi terbit/usang tidak pernah dihapus. Bila draf itu satu-satunya
 * versi (template baru yang batal), template ikut dihapus.
 */
final class HapusDrafTemplate
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    /**
     * @return bool True bila template ikut dihapus.
     */
    public function Jalankan(PenggunaPengelola $pelaku, TemplateSektorVersi $versi): bool
    {
        return DB::transaction(function () use ($pelaku, $versi): bool {
            // Urutan kunci sama di semua aksi template: baris TemplateSektor dulu, lalu versinya.
            $template = TemplateSektor::query()->lockForUpdate()->findOrFail($versi->IdTemplateSektor);
            $versi = TemplateSektorVersi::query()->lockForUpdate()->findOrFail($versi->Id);

            if ($versi->Status !== StatusTemplateSektor::Draf) {
                throw new PelanggaranAturanBisnis('BR-P03.2', 'Versi yang sudah terbit tidak bisa dihapus.');
            }

            $this->audit->Catat(
                'template.draf.hapus',
                $versi,
                nilaiLama: ['Kode' => $template->Kode, 'Versi' => $versi->Versi, 'Isi' => $versi->Isi],
                idPelaku: $pelaku->Id,
            );
            $versi->delete();

            if ($template->Versi()->doesntExist()) {
                $this->audit->Catat(
                    'template.hapus',
                    $template,
                    nilaiLama: $template->only(['Kode', 'Nama', 'Keterangan']),
                    idPelaku: $pelaku->Id,
                );
                $template->delete();

                return true;
            }

            return false;
        });
    }
}
