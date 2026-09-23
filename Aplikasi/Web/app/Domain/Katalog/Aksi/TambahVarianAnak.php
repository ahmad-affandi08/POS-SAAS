<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Data\DataVarianAnak;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Enum\SumberPerubahanKatalog;
use App\Domain\Katalog\Kueri\PemakaianSku;
use App\Domain\Katalog\Layanan\PemetaGalatUnikKatalog;
use App\Domain\Katalog\Layanan\PenyusunAnakVarian;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Tenant\Layanan\PastikanBatasPaket;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * F-03 varian satu per satu (dipakai impor): tambah satu anak ke induk varian. Definisi atribut induk diperluas
 * dengan nilai baru (atribut baru hanya selama induk belum punya anak). Idempoten per `KunciVarian`: anak yang
 * sudah ada dikembalikan apa adanya. `BatasSku` diperiksa; harga dasar lewat Tim 2 (sumber `Varian`, atau `Impor`
 * bila dari impor) dan butuh izin ubah harga.
 */
final class TambahVarianAnak
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PastikanBatasPaket $batasPaket,
        private readonly PemakaianSku $pemakaianSku,
        private readonly PenyusunAnakVarian $penyusun,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(Produk $induk, DataVarianAnak $data): Produk
    {
        $idTenant = $this->konteks->Wajib();

        try {
            return DB::transaction(fn (): Produk => $this->Tambah($idTenant, $induk, $data));
        } catch (UniqueConstraintViolationException $galat) {
            throw PemetaGalatUnikKatalog::Petakan($galat);
        }
    }

    private function Tambah(int $idTenant, Produk $induk, DataVarianAnak $data): Produk
    {
        $this->penguncian->Kunci($idTenant);
        $induk = Produk::query()->whereKey($induk->Id)->lockForUpdate()->firstOrFail();

        if ($induk->Jenis !== JenisProduk::IndukVarian) {
            throw new PelanggaranAturanBisnis('JenisTidakMendukung', 'Varian hanya bisa ditambahkan ke produk berjenis induk varian.', 'NamaInduk');
        }

        [$atribut, $definisi] = $this->penyusun->SelaraskanDefinisi($induk, $data->atribut);
        $ada = Produk::query()->where('IdInduk', $induk->Id)->where('KunciVarian', PenyusunAnakVarian::BuatKunci($atribut))->first();

        if ($ada !== null) {
            return $ada;
        }

        if ($data->hargaDasar !== null && ! $data->bolehUbahHarga) {
            throw new PelanggaranAturanBisnis('IzinHargaDiperlukan', 'Anda tidak punya izin mengubah harga jual. Kosongkan harga, atau minta pemilik mengisinya.', 'HargaDasar');
        }

        if ($data->jenis->CekDihitungBatasSku()) {
            $this->batasPaket->Pastikan($idTenant, 'BatasSku', fn (): int => $this->pemakaianSku->Hitung(), 1);
        }

        $induk->fill(['AtributVarian' => $definisi])->save();
        $sumberHarga = $data->sumber === SumberPerubahanKatalog::Impor ? 'Impor' : 'Varian';
        $anak = $this->penyusun->Buat($induk, $atribut, $data->sku, $data->jenis, $data->hargaDasar, $sumberHarga);

        if ($data->sumber !== SumberPerubahanKatalog::Impor) {
            $this->audit->Catat('produk.varian.tambah', $anak, null, [
                'IdInduk' => $induk->Id,
                'AtributVarian' => $atribut,
                'Sku' => $anak->Sku,
                'HargaDasar' => $data->hargaDasar?->KeString(),
            ]);
        }

        return $anak;
    }
}
