<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Referensi\Data\DataWilayah;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Referensi\Enum\TingkatWilayah;
use App\Domain\Referensi\Model\Wilayah;
use Illuminate\Support\Facades\DB;

/**
 * Memuat kode wilayah resmi secara massal (upsert per Kode) dalam satu transaksi: semua baris valid atau tidak ada
 * yang tersimpan. Dipakai perintah `pengelola:impor-wilayah` (P-02).
 */
final class ImporWilayah
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    /**
     * @param  list<DataWilayah>  $daftar
     * @return array{Baru: int, Diubah: int}
     */
    public function Jalankan(array $daftar, string $sumber): array
    {
        $provinsiDiBerkas = [];
        $kodeTerlihat = [];

        foreach ($daftar as $urutan => $data) {
            if (isset($kodeTerlihat[$data->kode])) {
                throw new PelanggaranAturanBisnis('KodeGanda', 'Baris '.($urutan + 1).": kode {$data->kode} muncul lebih dari sekali.");
            }

            $kodeTerlihat[$data->kode] = true;

            if ($data->tingkat === TingkatWilayah::Provinsi) {
                $provinsiDiBerkas[$data->kode] = true;
            }
        }

        foreach ($daftar as $urutan => $data) {
            try {
                SimpanWilayah::PastikanValid($data, indukBoleh: isset($provinsiDiBerkas[(string) $data->kodeInduk]));
            } catch (PelanggaranAturanBisnis $galat) {
                throw new PelanggaranAturanBisnis($galat->kode, 'Baris '.($urutan + 1)." ({$data->kode}): ".$galat->getMessage());
            }
        }

        return DB::transaction(function () use ($daftar, $sumber): array {
            $hasil = ['Baru' => 0, 'Diubah' => 0];
            $urut = $daftar;
            usort($urut, fn (DataWilayah $a, DataWilayah $b) => strcmp($a->kode, $b->kode));

            foreach ($urut as $data) {
                $wilayah = Wilayah::query()->firstOrNew(['Kode' => $data->kode]);
                $baru = ! $wilayah->exists;
                $wilayah->fill($data->KeLarik());

                if ($baru || $wilayah->isDirty()) {
                    $wilayah->save();
                    $hasil[$baru ? 'Baru' : 'Diubah']++;
                }
            }

            $this->audit->Catat('referensi.wilayah.impor', nilaiBaru: [...$hasil, 'Sumber' => $sumber]);

            return $hasil;
        });
    }
}
