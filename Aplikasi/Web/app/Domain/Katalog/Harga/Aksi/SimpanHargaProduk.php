<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Harga\Data\DataBarisHarga;
use App\Domain\Katalog\Harga\Data\HasilSimpanHarga;
use App\Domain\Katalog\Harga\Enum\SumberPerubahanHarga;
use App\Domain\Katalog\Harga\Layanan\PenyelarasBarisHarga;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukSatuan;
use Illuminate\Support\Facades\DB;
use App\Domain\Tenant\Layanan\PenguncianTenant;

/**
 * Menyimpan harga dasar & harga bertingkat (lapis 4–5 price engine F-03) per satuan produk, dengan riwayat harga
 * (BR-03.3). Dipakai form harga, form produk (harga awal satuan baru), generasi varian, impor, dan panduan awal F-01.
 *
 * - `$perSatuan` = `[IdProdukSatuan => list<DataBarisHarga>]`. Untuk setiap satuan yang disebut, seluruh baris dasar
 *   (`IdDaftarHarga` null) **diganti** dengan daftar itu; satuan yang tidak disebut tidak disentuh. Daftar kosong =
 *   harga dasar satuan dihapus (satuan tidak bisa dijual lagi).
 * - Validasi & beda: `PenyelarasBarisHarga` (daftar tidak kosong wajib punya `JumlahMinimum = 1`, `HargaDasarWajib`;
 *   satuan harus milik produk, `SatuanTidakDikenal`). Bidang galat `Satuan.{i}.Harga.{j}.{JumlahMinimum|Harga}`.
 * - Tanpa beda = tanpa tulis (idempoten). Audit `produk.harga.ubah`, kecuali sumber `Impor` (impor mengaudit per
 *   potongan).
 *
 * Urutan kunci: Tenant → baris `ProdukHarga` dasar satuan yang disebut (FOR UPDATE).
 */
final class SimpanHargaProduk
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
        private readonly PenyelarasBarisHarga $penyelaras,
    ) {}

    /**
     * @param  array<int, list<DataBarisHarga>>  $perSatuan
     */
    public function Jalankan(Produk $produk, array $perSatuan, SumberPerubahanHarga $sumber): HasilSimpanHarga
    {
        $idTenant = $this->konteks->Wajib();

        if ($perSatuan === []) {
            return new HasilSimpanHarga(0, 0, 0);
        }

        return DB::transaction(function () use ($idTenant, $produk, $perSatuan, $sumber): HasilSimpanHarga {
            $this->penguncian->Kunci($idTenant);
            $satuan = ProdukSatuan::query()
                ->where('IdProduk', $produk->Id)
                ->whereIn('Id', array_keys($perSatuan))
                ->with('SatuanUnit')
                ->get()
                ->keyBy('Id');
            $satuanValid = $this->penyelaras->Validasi($perSatuan, $satuan, true);
            $hasil = $this->penyelaras->Selaraskan($perSatuan, $satuanValid, null, $sumber);

            if ($hasil->hasil->CekAdaPerubahan() && $sumber !== SumberPerubahanHarga::Impor) {
                $this->audit->Catat('produk.harga.ubah', $produk, ['Harga' => $hasil->nilaiLama], ['Harga' => $hasil->nilaiBaru, 'Sumber' => $sumber->value]);
            }

            return $hasil->hasil;
        });
    }
}
