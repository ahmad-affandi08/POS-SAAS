<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Referensi\Data\DataSatuanStandar;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Referensi\Model\SatuanStandar;
use Illuminate\Support\Facades\DB;

/**
 * Membuat atau mengubah satuan standar (P-02). Tidak dihapus: yang tidak dipakai lagi dinonaktifkan.
 */
final class SimpanSatuanStandar
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, DataSatuanStandar $data, ?SatuanStandar $satuan = null): SatuanStandar
    {
        return DB::transaction(function () use ($pelaku, $data, $satuan): SatuanStandar {
            if ($satuan !== null && $satuan->Kode !== $data->kode) {
                throw new PelanggaranAturanBisnis('KodeTidakBisaDiubah', 'Kode tidak bisa diubah.', 'Kode');
            }

            if ($satuan === null && SatuanStandar::query()->where('Kode', $data->kode)->exists()) {
                throw new PelanggaranAturanBisnis('KodeSudahAda', "Kode {$data->kode} sudah ada.", 'Kode');
            }

            $nilaiLama = $satuan === null ? null : [
                'Kode' => $satuan->Kode,
                'Nama' => $satuan->Nama,
                'Simbol' => $satuan->Simbol,
                'BolehDesimal' => $satuan->BolehDesimal,
                'Aktif' => $satuan->Aktif,
            ];
            $satuan ??= new SatuanStandar;
            $satuan->fill($data->KeLarik())->save();

            $this->audit->Catat(
                $nilaiLama === null ? 'referensi.satuan.buat' : 'referensi.satuan.ubah',
                $satuan,
                nilaiLama: $nilaiLama,
                nilaiBaru: $data->KeLarik(),
                idPelaku: $pelaku->Id,
            );

            return $satuan;
        });
    }
}
