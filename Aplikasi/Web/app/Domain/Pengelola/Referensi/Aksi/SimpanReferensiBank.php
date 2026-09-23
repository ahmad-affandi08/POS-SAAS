<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Referensi\Data\DataReferensiBank;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Referensi\Model\ReferensiBank;
use Illuminate\Support\Facades\DB;

/**
 * Membuat atau mengubah referensi pembayaran (P-02). Tidak dihapus: yang tidak dipakai lagi dinonaktifkan.
 */
final class SimpanReferensiBank
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, DataReferensiBank $data, ?ReferensiBank $referensi = null): ReferensiBank
    {
        return DB::transaction(function () use ($pelaku, $data, $referensi): ReferensiBank {
            if ($referensi !== null && $referensi->Kode !== $data->kode) {
                throw new PelanggaranAturanBisnis('KodeTidakBisaDiubah', 'Kode tidak bisa diubah.', 'Kode');
            }

            if ($referensi === null && ReferensiBank::query()->where('Kode', $data->kode)->exists()) {
                throw new PelanggaranAturanBisnis('KodeSudahAda', "Kode {$data->kode} sudah ada.", 'Kode');
            }

            $nilaiLama = $referensi === null ? null : [
                'Kode' => $referensi->Kode,
                'Nama' => $referensi->Nama,
                'Jenis' => $referensi->Jenis->value,
                'Aktif' => $referensi->Aktif,
            ];
            $referensi ??= new ReferensiBank;
            $referensi->fill($data->KeLarik())->save();

            $this->audit->Catat(
                $nilaiLama === null ? 'referensi.bank.buat' : 'referensi.bank.ubah',
                $referensi,
                nilaiLama: $nilaiLama,
                nilaiBaru: $data->KeLarik(),
                idPelaku: $pelaku->Id,
            );

            return $referensi;
        });
    }
}
