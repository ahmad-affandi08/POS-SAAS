<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TemplateSektor\Aksi;

use App\Domain\PanduanAwal\Enum\StatusTemplateSektor;
use App\Domain\PanduanAwal\Model\TemplateSektor;
use App\Domain\PanduanAwal\Model\TemplateSektorVersi;
use App\Domain\Pengelola\TemplateSektor\Layanan\ValidatorTemplate;
use Illuminate\Support\Facades\DB;

/**
 * Menjalankan ulang validasi otomatis draf (P-03 langkah 3), misal setelah tarif pajak atau katalog fitur berubah.
 * Versi terbit tidak divalidasi ulang karena isinya tetap.
 */
final class ValidasiVersiTemplate
{
    public function __construct(private readonly ValidatorTemplate $validator) {}

    /**
     * @return array{Lolos: bool, Galat: list<array{Bagian: string, Pesan: string}>}
     */
    public function Jalankan(TemplateSektorVersi $versi): array
    {
        return DB::transaction(function () use ($versi): array {
            // Urutan kunci sama di semua aksi template: baris TemplateSektor dulu, lalu versinya.
            TemplateSektor::query()->lockForUpdate()->findOrFail($versi->IdTemplateSektor);
            $versi = TemplateSektorVersi::query()->lockForUpdate()->findOrFail($versi->Id);
            $hasil = $this->validator->Validasi($versi->Isi);

            if ($versi->Status === StatusTemplateSektor::Draf) {
                $versi->update(['HasilValidasi' => $hasil, 'DivalidasiPada' => now()]);
            }

            return $hasil;
        });
    }
}
