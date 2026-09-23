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
 * Menerbitkan draf template (P-03 langkah 5). Validasi dijalankan ulang saat itu juga karena data P-02/P-04 bisa
 * berubah sejak draf terakhir disimpan; gagal validasi = tidak terbit (BR-P03.3), dan hasilnya tetap disimpan agar
 * penyusun melihat penyebabnya. Versi terbit sebelumnya menjadi Usang: tenant baru memakai versi ini, tenant lama
 * tidak berubah tanpa persetujuannya (BR-P03.1).
 */
final class TerbitkanTemplate
{
    public function __construct(
        private readonly PencatatAuditPengelola $audit,
        private readonly ValidatorTemplate $validator,
    ) {}

    public function Jalankan(PenggunaPengelola $pelaku, TemplateSektorVersi $versi): TemplateSektorVersi
    {
        $hasil = DB::transaction(function () use ($pelaku, $versi): array {
            // Urutan kunci sama di semua aksi template: baris TemplateSektor dulu, lalu versinya.
            $template = TemplateSektor::query()->lockForUpdate()->findOrFail($versi->IdTemplateSektor);
            $versi = TemplateSektorVersi::query()->lockForUpdate()->findOrFail($versi->Id);

            if ($versi->Status !== StatusTemplateSektor::Draf) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', "Versi {$versi->Versi} sudah {$versi->Status->AmbilLabel()}.");
            }

            $validasi = $this->validator->Validasi($versi->Isi);
            $versi->update(['HasilValidasi' => $validasi, 'DivalidasiPada' => now()]);

            if (! $validasi['Lolos']) {
                return ['Versi' => $versi, 'Lolos' => false, 'JumlahGalat' => count($validasi['Galat'])];
            }

            $lama = $template->Versi()->where('Status', StatusTemplateSektor::Terbit->value)->first();
            $lama?->update(['Status' => StatusTemplateSektor::Usang, 'DiusangkanPada' => now()]);

            $versi->update([
                'Status' => StatusTemplateSektor::Terbit,
                'IdPenggunaPengelolaPenerbit' => $pelaku->Id,
                'DiterbitkanPada' => now(),
            ]);

            $this->audit->Catat(
                'template.terbitkan',
                $versi,
                nilaiLama: $lama === null ? null : ['VersiTerbit' => $lama->Versi],
                nilaiBaru: ['Kode' => $template->Kode, 'VersiTerbit' => $versi->Versi],
                idPelaku: $pelaku->Id,
            );

            return ['Versi' => $versi, 'Lolos' => true, 'JumlahGalat' => 0];
        });

        if (! $hasil['Lolos']) {
            throw new PelanggaranAturanBisnis(
                'BR-P03.3',
                "Template belum bisa terbit: {$hasil['JumlahGalat']} masalah validasi. Perbaiki lalu terbitkan lagi.",
            );
        }

        return $hasil['Versi'];
    }
}
