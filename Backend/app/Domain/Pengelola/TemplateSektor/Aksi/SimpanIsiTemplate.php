<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TemplateSektor\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\PanduanAwal\Enum\StatusTemplateSektor;
use App\Domain\PanduanAwal\Model\TemplateSektor;
use App\Domain\PanduanAwal\Model\TemplateSektorVersi;
use App\Domain\Pengelola\TemplateSektor\Enum\BagianTemplate;
use App\Domain\Pengelola\TemplateSektor\Layanan\ValidatorTemplate;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan satu bagian isi draf template (P-03 langkah 2, BR-P03.5). Hanya kunci milik bagian tersebut yang
 * ditimpa, sehingga Konten & Legal dan Keuangan bisa menyunting draf yang sama tanpa saling menimpa. Validasi
 * otomatis dijalankan ulang dan hasilnya disimpan; draf yang belum lolos tetap boleh disimpan (BR-P03.3 berlaku
 * saat terbit).
 */
final class SimpanIsiTemplate
{
    public function __construct(
        private readonly PencatatAuditPengelola $audit,
        private readonly ValidatorTemplate $validator,
    ) {}

    /**
     * @param  array<string, mixed>  $isiBagian
     */
    public function Jalankan(PenggunaPengelola $pelaku, TemplateSektorVersi $versi, BagianTemplate $bagian, array $isiBagian): TemplateSektorVersi
    {
        return DB::transaction(function () use ($pelaku, $versi, $bagian, $isiBagian): TemplateSektorVersi {
            // Urutan kunci sama di semua aksi template: baris TemplateSektor dulu, lalu versinya.
            TemplateSektor::query()->lockForUpdate()->findOrFail($versi->IdTemplateSektor);
            $versi = TemplateSektorVersi::query()->lockForUpdate()->findOrFail($versi->Id);

            if ($versi->Status !== StatusTemplateSektor::Draf) {
                throw new PelanggaranAturanBisnis('BR-P03.4', 'Versi yang sudah terbit tidak bisa diubah. Duplikasi menjadi draf versi baru.');
            }

            $kunci = array_flip($bagian->AmbilKunciIsi());
            $isiBaru = array_intersect_key($isiBagian, $kunci);
            $isiLama = array_intersect_key($versi->Isi, $kunci);
            $isi = [...$versi->Isi, ...$isiBaru];

            $versi->update([
                'Isi' => $isi,
                'HasilValidasi' => $this->validator->Validasi($isi),
                'DivalidasiPada' => now(),
            ]);

            $berubah = array_keys(array_filter($isiBaru, fn (mixed $nilai, string $nama) => json_encode($isiLama[$nama] ?? null) !== json_encode($nilai), ARRAY_FILTER_USE_BOTH));

            if ($berubah !== []) {
                $this->audit->Catat(
                    $bagian->AmbilAksiAudit(),
                    $versi,
                    nilaiLama: array_intersect_key($isiLama, array_flip($berubah)),
                    nilaiBaru: array_intersect_key($isiBaru, array_flip($berubah)),
                    idPelaku: $pelaku->Id,
                );
            }

            return $versi;
        });
    }
}
