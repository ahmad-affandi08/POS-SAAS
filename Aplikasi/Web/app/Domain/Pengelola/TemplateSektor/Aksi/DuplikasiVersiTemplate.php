<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TemplateSektor\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\PanduanAwal\Enum\StatusTemplateSektor;
use App\Domain\PanduanAwal\Model\TemplateSektor;
use App\Domain\PanduanAwal\Model\TemplateSektorVersi;
use App\Domain\Pengelola\TemplateSektor\Layanan\ValidatorTemplate;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Support\Facades\DB;

/**
 * Menyalin versi terbit/usang menjadi draf versi baru (P-03 langkah 1). Satu template hanya boleh punya satu draf
 * (BR-P03.4); baris template dikunci agar dua permintaan bersamaan tidak membuat dua draf.
 */
final class DuplikasiVersiTemplate
{
    public function __construct(
        private readonly PencatatAuditPengelola $audit,
        private readonly ValidatorTemplate $validator,
    ) {}

    public function Jalankan(PenggunaPengelola $pelaku, TemplateSektorVersi $asal): TemplateSektorVersi
    {
        return DB::transaction(function () use ($pelaku, $asal): TemplateSektorVersi {
            // Urutan kunci sama di semua aksi template: baris TemplateSektor dulu, lalu versinya.
            $template = TemplateSektor::query()->lockForUpdate()->findOrFail($asal->IdTemplateSektor);
            $asal = TemplateSektorVersi::query()->lockForUpdate()->findOrFail($asal->Id);

            if ($asal->Status === StatusTemplateSektor::Draf) {
                throw new PelanggaranAturanBisnis('BR-P03.4', 'Versi ini masih draf. Ubah draf itu langsung.');
            }

            if ($template->Versi()->where('Status', StatusTemplateSektor::Draf->value)->exists()) {
                throw new PelanggaranAturanBisnis('BR-P03.4', "Template {$template->Kode} sudah punya draf. Selesaikan atau hapus draf itu dulu.");
            }

            $versiBaru = (int) $template->Versi()->max('Versi') + 1;
            $draf = $template->Versi()->create([
                'Versi' => $versiBaru,
                'Status' => StatusTemplateSektor::Draf,
                'Isi' => $asal->Isi,
                'IdVersiAsal' => $asal->Id,
                'HasilValidasi' => $this->validator->Validasi($asal->Isi),
                'DivalidasiPada' => now(),
            ]);

            $this->audit->Catat(
                'template.versi.duplikasi',
                $draf,
                nilaiBaru: ['Kode' => $template->Kode, 'Versi' => $versiBaru, 'VersiAsal' => $asal->Versi],
                idPelaku: $pelaku->Id,
            );

            return $draf;
        });
    }
}
