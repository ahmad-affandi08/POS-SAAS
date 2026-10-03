<?php

declare(strict_types=1);

namespace App\Domain\Lisensi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Lisensi\Data\DataLisensi;
use App\Domain\Lisensi\Enum\EdisiAplikasi;
use App\Domain\Lisensi\Kontrak\PenyiapDataBawaan;
use App\Domain\Lisensi\Kueri\LisensiBerlaku;
use App\Domain\Lisensi\Layanan\PenandaLisensi;
use App\Domain\Lisensi\Model\LisensiTerpasang;
use App\Domain\Organisasi\Data\DataPemilikBaru;
use App\Domain\Tenant\Aksi\SiapkanTenantLisensi;
use Illuminate\Support\Facades\DB;

/**
 * D-35: memasang berkas lisensi di server pembeli. Pemasangan pertama sekaligus membuat satu-satunya usaha beserta
 * Owner-nya (`SiapkanTenantLisensi`); pemasangan berikutnya hanya mengganti berkas (misal tambah outlet/perangkat atau
 * pindah domain) tanpa menyentuh data usaha. Berkas diverifikasi tanda tangannya sebelum apa pun disimpan. Data master
 * platform (pajak, satuan, wilayah, katalog fitur, template sektor) disiapkan & diterbitkan lebih dulu, karena panduan
 * awal usaha baru membutuhkannya dan server pembeli tidak punya konsol.
 */
final class PasangLisensi
{
    public function __construct(
        private readonly PenandaLisensi $penanda,
        private readonly SiapkanTenantLisensi $siapkanTenant,
        private readonly PenyiapDataBawaan $siapkanData,
    ) {}

    /**
     * @param  DataPemilikBaru|null  $pemilik  wajib pada pemasangan pertama; diabaikan saat mengganti lisensi
     */
    public function Jalankan(string $isiBerkas, ?string $namaUsaha = null, ?DataPemilikBaru $pemilik = null): DataLisensi
    {
        if (! EdisiAplikasi::CekLisensi()) {
            throw new PelanggaranAturanBisnis('D-35', 'Lisensi hanya bisa dipasang di edisi Lisensi (EDISI=Lisensi).');
        }

        $data = $this->penanda->Baca($isiBerkas);

        DB::transaction(function () use ($data, $isiBerkas, $namaUsaha, $pemilik): void {
            $pertama = ! LisensiTerpasang::query()->lockForUpdate()->exists();

            if ($pertama) {
                if ($namaUsaha === null || trim($namaUsaha) === '' || $pemilik === null) {
                    throw new PelanggaranAturanBisnis('D-35', 'Pemasangan pertama butuh nama usaha dan data Owner.');
                }

                $this->siapkanData->Jalankan();
                $this->siapkanTenant->Jalankan(trim($namaUsaha), $pemilik);
            }

            LisensiTerpasang::query()->create([
                'Nomor' => $data->nomor,
                'Domain' => $data->domain,
                'IsiBerkas' => $isiBerkas,
                'DipasangPada' => now(),
            ]);
        });

        LisensiBerlaku::Lupakan();

        return $data;
    }
}
