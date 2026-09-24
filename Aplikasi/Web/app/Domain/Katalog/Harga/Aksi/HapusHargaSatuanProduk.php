<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Enum\EntitasKatalog;
use App\Domain\Katalog\Harga\Enum\SumberPerubahanHarga;
use App\Domain\Katalog\Harga\Layanan\PencatatRiwayatHarga;
use App\Domain\Katalog\Layanan\PencatatPenghapusanKatalog;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukHarga;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * Menghapus semua harga satuan produk (dasar, bertingkat, dan di semua daftar harga) sebelum satuan produk atau
 * produknya dihapus (dipanggil Tim 1 dari `SimpanProduk`/`HapusProduk`). Setiap baris dicatat di `RiwayatHarga`
 * (`HargaBaru` null, BR-03.3) dan `PenghapusanKatalog` (katalog POS). Audit `produk.harga.ubah` kecuali sumber `Impor`.
 *
 * Urutan kunci: Tenant → baris `ProdukHarga` satuan itu (FOR UPDATE).
 */
final class HapusHargaSatuanProduk
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
        private readonly PencatatRiwayatHarga $riwayat,
        private readonly PencatatPenghapusanKatalog $penghapusan,
    ) {}

    public function Jalankan(ProdukSatuan $satuan, SumberPerubahanHarga $sumber): void
    {
        $idTenant = $this->konteks->Wajib();

        DB::transaction(function () use ($idTenant, $satuan, $sumber): void {
            $this->penguncian->Kunci($idTenant);
            $daftar = ProdukHarga::query()->where('IdProdukSatuan', $satuan->Id)->with('DaftarHarga')->orderBy('Id')->lockForUpdate()->get();

            if ($daftar->isEmpty()) {
                return;
            }

            $nilaiLama = [];

            foreach ($daftar as $baris) {
                $this->riwayat->Catat($baris->IdProduk, $satuan->Id, $satuan->IdSatuan, $baris->IdDaftarHarga, $baris->JumlahMinimum, $baris->Harga, null, $sumber);
                $this->penghapusan->Catat(EntitasKatalog::ProdukHarga, $baris->Uuid);
                $nilaiLama[] = ['UuidDaftarHarga' => $baris->DaftarHarga?->Uuid, 'JumlahMinimum' => $baris->JumlahMinimum, 'Harga' => $baris->Harga];
                $baris->delete();
            }

            if ($sumber !== SumberPerubahanHarga::Impor) {
                $produk = Produk::query()->withTrashed()->find($satuan->IdProduk);
                $this->audit->Catat(
                    'produk.harga.ubah',
                    $produk,
                    ['Harga' => [$satuan->Uuid => $nilaiLama]],
                    ['Harga' => [$satuan->Uuid => []], 'Sumber' => $sumber->value],
                );
            }
        });
    }
}
