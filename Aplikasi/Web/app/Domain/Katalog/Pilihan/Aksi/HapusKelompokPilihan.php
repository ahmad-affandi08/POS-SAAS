<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Pilihan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Enum\EntitasKatalog;
use App\Domain\Katalog\Layanan\PencatatPenghapusanKatalog;
use App\Domain\Katalog\Pilihan\Model\KelompokPilihan;
use App\Domain\Katalog\Pilihan\Model\Pilihan;
use App\Domain\Katalog\Pilihan\Model\ProdukKelompokPilihan;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * Menghapus kelompok pilihan (F-03 C.4): lepas dari semua produk, hapus pilihannya, lalu kelompoknya, masing-masing
 * dengan jejak penghapusan untuk sinkron POS. Penjualan lama menyimpan snapshot nama & harga pilihan (F-07).
 */
final class HapusKelompokPilihan
{
    public function __construct(
        private readonly PencatatAudit $audit,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatPenghapusanKatalog $penghapusan,
        private readonly KonteksTenant $konteks,
    ) {}

    public function Jalankan(KelompokPilihan $kelompok): void
    {
        DB::transaction(function () use ($kelompok): void {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $kelompok = KelompokPilihan::query()->lockForUpdate()->findOrFail($kelompok->Id);

            $tautan = ProdukKelompokPilihan::query()->where('IdKelompokPilihan', $kelompok->Id)->lockForUpdate()->get();
            $pilihan = Pilihan::query()->where('IdKelompokPilihan', $kelompok->Id)->orderBy('Urutan')->lockForUpdate()->get();

            foreach ($tautan as $baris) {
                $this->penghapusan->Catat(EntitasKatalog::ProdukKelompokPilihan, $baris->Uuid);
                $baris->delete();
            }

            foreach ($pilihan as $baris) {
                $this->penghapusan->Catat(EntitasKatalog::Pilihan, $baris->Uuid);
                $baris->delete();
            }

            $this->audit->Catat('kelompok-pilihan.hapus', $kelompok, nilaiLama: [
                'Nama' => $kelompok->Nama,
                'Pilihan' => $pilihan->pluck('Nama')->all(),
                'JumlahProduk' => $tautan->count(),
            ]);
            $this->penghapusan->Catat(EntitasKatalog::KelompokPilihan, $kelompok->Uuid);
            $kelompok->delete();
        });
    }
}
