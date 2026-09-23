<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Data\DataBatasStok;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukGudang;
use App\Domain\Organisasi\Kueri\GudangTenant;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-03: stok minimum/maksimum produk per lokasi stok (satuan dasar), mengganti seluruh set. Hanya untuk jenis yang
 * punya stok; lokasi harus lokasi stok aktif tenant (`GudangTidakDikenal`); minimum ≤ maksimum
 * (`BatasStokTidakValid`). Baris yang keduanya kosong dihapus.
 */
final class SimpanBatasStokProduk
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly GudangTenant $gudangTenant,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  list<DataBatasStok>  $baris
     */
    public function Jalankan(Produk $produk, array $baris): void
    {
        DB::transaction(function () use ($produk, $baris): void {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $produk = Produk::query()->whereKey($produk->Id)->lockForUpdate()->firstOrFail();

            if (! $produk->Jenis->CekPunyaStok()) {
                throw new PelanggaranAturanBisnis('JenisTidakMendukung', "Produk {$produk->Jenis->AmbilLabel()} tidak punya stok, jadi tidak memakai batas stok.", 'Baris');
            }

            $idGudangAktif = array_column($this->gudangTenant->AmbilAktif(), 'Id');
            $diinginkan = [];

            foreach ($baris as $i => $satu) {
                if (! in_array($satu->idGudang, $idGudangAktif, true) || isset($diinginkan[$satu->idGudang])) {
                    throw new PelanggaranAturanBisnis('GudangTidakDikenal', 'Lokasi stok tidak ditemukan, diarsipkan, atau dipilih dua kali.', "Baris.{$i}.UuidGudang");
                }

                if ($satu->minimum?->BernilaiNegatif() === true || $satu->maksimum?->BernilaiNegatif() === true
                    || ($satu->minimum !== null && $satu->maksimum !== null && $satu->minimum->Bandingkan($satu->maksimum) > 0)) {
                    throw new PelanggaranAturanBisnis('BatasStokTidakValid', 'Stok minimum tidak boleh negatif dan tidak boleh lebih besar dari stok maksimum.', "Baris.{$i}.StokMaksimum");
                }

                $diinginkan[$satu->idGudang] = $satu;
            }

            $ada = ProdukGudang::query()->where('IdProduk', $produk->Id)->lockForUpdate()->get()->keyBy('IdGudang');
            $lama = $ada->map(fn (ProdukGudang $g): array => ['IdGudang' => $g->IdGudang, 'StokMinimum' => $g->StokMinimum, 'StokMaksimum' => $g->StokMaksimum])->values()->all();

            foreach ($ada as $idGudang => $barisAda) {
                $satu = $diinginkan[$idGudang] ?? null;

                if ($satu === null || ($satu->minimum === null && $satu->maksimum === null)) {
                    $barisAda->delete();
                }
            }

            foreach ($diinginkan as $idGudang => $satu) {
                if ($satu->minimum === null && $satu->maksimum === null) {
                    continue;
                }

                ($ada->get($idGudang) ?? new ProdukGudang(['IdProduk' => $produk->Id, 'IdGudang' => $idGudang]))
                    ->fill(['StokMinimum' => $satu->minimum?->KeString(), 'StokMaksimum' => $satu->maksimum?->KeString()])
                    ->save();
            }

            $this->audit->Catat('produk.batas-stok.ubah', $produk, ['Baris' => $lama], ['Baris' => array_values(array_map(fn (DataBatasStok $d): array => [
                'IdGudang' => $d->idGudang,
                'StokMinimum' => $d->minimum?->KeString(),
                'StokMaksimum' => $d->maksimum?->KeString(),
            ], $diinginkan))]);
        });
    }
}
