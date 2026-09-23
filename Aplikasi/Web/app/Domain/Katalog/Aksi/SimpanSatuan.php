<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Data\DataSatuan;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\Satuan;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-03: buat atau ubah satuan tenant. Nama unik tanpa beda huruf besar/kecil (`SatuanGanda`). `BolehDesimal` tidak
 * bisa diubah bila satuan menjadi satuan dasar produk (`SatuanDesimalTerkunci`): jumlah stok & resep yang sudah
 * tercatat bergantung padanya.
 */
final class SimpanSatuan
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(?Satuan $satuan, DataSatuan $data): Satuan
    {
        return DB::transaction(function () use ($satuan, $data): Satuan {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $satuan = $satuan === null ? null : Satuan::query()->whereKey($satuan->Id)->lockForUpdate()->firstOrFail();
            $nama = trim($data->nama);
            $simbol = trim($data->simbol);

            if ($nama === '' || mb_strlen($nama) > 100 || $simbol === '' || mb_strlen($simbol) > 20) {
                throw new PelanggaranAturanBisnis('NamaTidakValid', 'Nama satuan (maks. 100 karakter) dan simbol (maks. 20 karakter) wajib diisi.', 'Nama');
            }

            $ganda = Satuan::query()
                ->when($satuan !== null, fn ($kueri) => $kueri->whereKeyNot($satuan?->Id))
                ->whereRaw('LOWER(Nama) = ?', [mb_strtolower($nama)])
                ->exists();

            if ($ganda) {
                throw new PelanggaranAturanBisnis('SatuanGanda', "Satuan {$nama} sudah ada.", 'Nama');
            }

            if ($satuan !== null && $satuan->BolehDesimal !== $data->bolehDesimal
                && Produk::query()->withTrashed()->where('IdSatuanDasar', $satuan->Id)->exists()) {
                throw new PelanggaranAturanBisnis('SatuanDesimalTerkunci', "Satuan {$satuan->Nama} sudah menjadi satuan dasar produk, jadi pilihan desimalnya tidak bisa diubah. Buat satuan baru bila perlu.", 'BolehDesimal');
            }

            $lama = $satuan?->only(['Nama', 'Simbol', 'BolehDesimal']);
            $satuan ??= new Satuan;
            $satuan->fill(['Nama' => $nama, 'Simbol' => $simbol, 'BolehDesimal' => $data->bolehDesimal])->save();

            $this->audit->Catat($lama === null ? 'satuan.buat' : 'satuan.ubah', $satuan, $lama, $satuan->only(['Nama', 'Simbol', 'BolehDesimal']));

            return $satuan;
        });
    }
}
