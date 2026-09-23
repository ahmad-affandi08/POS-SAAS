<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Pilihan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Enum\EntitasKatalog;
use App\Domain\Katalog\Layanan\PencatatPenghapusanKatalog;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Pilihan\Model\KelompokPilihan;
use App\Domain\Katalog\Pilihan\Model\ProdukKelompokPilihan;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * Memasang kelompok pilihan ke produk sesuai urutan (F-03 C.4): daftar lengkap menggantikan pemasangan lama.
 * Hanya untuk jenis yang boleh punya pilihan (`CekBolehPilihan`) dan bukan anak varian (anak mewarisi kelompok
 * induk). Kelompok harus milik tenant ini. Idempoten: kiriman sama tidak mengubah apa pun.
 */
final class AturKelompokPilihanProduk
{
    public function __construct(
        private readonly PencatatAudit $audit,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatPenghapusanKatalog $penghapusan,
        private readonly KonteksTenant $konteks,
    ) {}

    /**
     * @param  list<int>  $idKelompokPilihan
     */
    public function Jalankan(Produk $produk, array $idKelompokPilihan): void
    {
        DB::transaction(function () use ($produk, $idKelompokPilihan): void {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $produk = Produk::query()->lockForUpdate()->findOrFail($produk->Id);

            if (! $produk->Jenis->CekBolehPilihan() || $produk->IdInduk !== null) {
                throw new PelanggaranAturanBisnis(
                    'JenisTidakMendukung',
                    $produk->IdInduk !== null
                        ? 'Anak varian memakai kelompok pilihan induknya. Atur pilihan di produk induk.'
                        : "Produk jenis {$produk->Jenis->AmbilLabel()} tidak bisa punya pilihan.",
                    'KelompokPilihan',
                );
            }

            $idKelompokPilihan = array_values(array_unique($idKelompokPilihan));
            $ditemukan = KelompokPilihan::query()->whereKey($idKelompokPilihan)->lockForUpdate()->pluck('Nama', 'Id');

            foreach ($idKelompokPilihan as $i => $id) {
                if (! $ditemukan->has($id)) {
                    throw new PelanggaranAturanBisnis('KelompokPilihanTidakDikenal', 'Kelompok pilihan tidak ditemukan.', "KelompokPilihan.{$i}");
                }
            }

            $lama = ProdukKelompokPilihan::query()->where('IdProduk', $produk->Id)->orderBy('Urutan')->lockForUpdate()->get()->keyBy('IdKelompokPilihan');
            $sebelum = $lama->keys()->map(fn (mixed $id): mixed => $ditemukan->get($id) ?? $id)->values()->all();

            foreach ($lama as $idKelompok => $baris) {
                if (! in_array($idKelompok, $idKelompokPilihan, true)) {
                    $this->penghapusan->Catat(EntitasKatalog::ProdukKelompokPilihan, $baris->Uuid);
                    $baris->delete();
                }
            }

            foreach ($idKelompokPilihan as $urutan => $id) {
                $baris = $lama->get($id);

                if ($baris instanceof ProdukKelompokPilihan) {
                    $baris->fill(['Urutan' => $urutan])->save();

                    continue;
                }

                ProdukKelompokPilihan::query()->create(['IdProduk' => $produk->Id, 'IdKelompokPilihan' => $id, 'Urutan' => $urutan]);
            }

            $sesudah = array_map(fn (int $id): mixed => $ditemukan->get($id), $idKelompokPilihan);

            if ($sebelum !== $sesudah) {
                $this->audit->Catat('produk.pilihan.ubah', $produk, nilaiLama: ['KelompokPilihan' => $sebelum], nilaiBaru: ['KelompokPilihan' => $sesudah]);
            }
        });
    }
}
