<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukHabis;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use Illuminate\Support\Facades\DB;

/**
 * F-17 BR-17.2: tandai produk habis ("86") atau tersedia lagi di satu outlet. Produk habis hilang dari menu self-order
 * QR meja dan toko online outlet itu, dan keranjang yang masih memuatnya ditolak saat dihitung ulang/checkout
 * (`ProdukTidakTersedia`). Pesanan yang sudah masuk tidak disentuh. Tidak ada efek stok maupun jurnal.
 *
 * Idempoten: menandai habis yang sudah habis (atau tersedia yang sudah tersedia) tidak menulis apa pun dan tidak
 * mencatat audit. Pelaku dari POS wajib anggota outlet dengan izin `produk.kelola`, `penjualan.buat`, atau
 * `pesanan.meja.catat`; dari back-office, kontrolernya sudah menegakkan izin dan melewatkan `$uuidPengguna` null.
 * Hanya produk yang bisa dijual langsung (bukan induk varian) yang boleh ditandai; anak varian ditandai satu per satu.
 */
final class UbahKetersediaanProduk
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly AnggotaOutlet $anggota,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @return bool keadaan akhir: true = habis
     */
    public function Jalankan(int $idOutlet, string $uuidProduk, bool $habis, ?string $uuidPengguna = null, ?int $idPenggunaBackOffice = null): bool
    {
        $idTenant = $this->konteks->Wajib();
        $idPengguna = $idPenggunaBackOffice;

        if ($uuidPengguna !== null) {
            $pelaku = $this->anggota->Cari($idTenant, $uuidPengguna, $idOutlet)
                ?? throw new PelanggaranAturanBisnis('KasirTidakDitemukan', 'Pengguna ini tidak terdaftar di outlet ini.', 'UuidPengguna');

            if (! $pelaku->CekIzin(IzinTenant::ProdukKelola->value) && ! $pelaku->CekIzin(IzinTenant::PenjualanBuat->value) && ! $pelaku->CekIzin(IzinTenant::PesananMejaCatat->value)) {
                throw new PelanggaranAturanBisnis('TanpaIzin', "{$pelaku->nama} tidak punya izin menandai produk habis.", 'UuidPengguna', 403);
            }

            $idPengguna = $pelaku->id;
        }

        return DB::transaction(function () use ($idOutlet, $uuidProduk, $habis, $idPengguna): bool {
            $produk = Produk::query()->where('Uuid', $uuidProduk)->first()
                ?? throw new PelanggaranAturanBisnis('ProdukTidakDitemukan', 'Produk tidak ditemukan.', 'UuidProduk', 404);

            if ($produk->Jenis === JenisProduk::IndukVarian) {
                throw new PelanggaranAturanBisnis('ProdukIndukVarian', 'Tandai habis per varian, bukan produk induknya.', 'UuidProduk');
            }

            $ada = ProdukHabis::query()->where('IdProduk', $produk->Id)->where('IdOutlet', $idOutlet)->lockForUpdate()->first();

            if ($habis && $ada === null) {
                ProdukHabis::query()->create(['IdProduk' => $produk->Id, 'IdOutlet' => $idOutlet, 'IdPengguna' => $idPengguna]);
                $this->audit->Catat('produk.habis', $produk, nilaiBaru: ['IdOutlet' => $idOutlet, 'Habis' => true], idPengguna: $idPengguna);
            } elseif (! $habis && $ada !== null) {
                $ada->delete();
                $this->audit->Catat('produk.tersedia', $produk, nilaiBaru: ['IdOutlet' => $idOutlet, 'Habis' => false], idPengguna: $idPengguna);
            }

            return $habis;
        });
    }
}
