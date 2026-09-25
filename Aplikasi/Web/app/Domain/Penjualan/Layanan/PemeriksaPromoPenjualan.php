<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Katalog\Data\DataProdukPenjualan;
use App\Domain\Organisasi\Data\DataOutletPenjualan;
use App\Domain\Penjualan\Data\DataBarisPenjualanPos;
use App\Domain\Penjualan\Data\DataPenjualanPos;
use App\Domain\Penjualan\Kalkulasi\BarisPromo;
use App\Domain\Penjualan\Kalkulasi\DataKalkulasi;
use App\Domain\Penjualan\Kalkulasi\KonteksPromo;
use App\Domain\Penjualan\Kalkulasi\MesinPromo;
use App\Domain\Penjualan\Kalkulasi\PromoTerpakai;
use App\Domain\Promo\Kueri\PromoBerlaku;

/**
 * Validasi ulang promo penjualan POS (F-16c): `MesinPromo` dijalankan dengan definisi promo server (kuota tersisa
 * terkini, waktu transaksi di jam lokal outlet, kanal, outlet, tier pelanggan) lalu dibandingkan dengan promo yang
 * diterapkan perangkat. Beda (promo diubah/berakhir/kuota habis saat offline) = keterangan untuk tinjauan; penjualan
 * tetap memakai hitungan perangkat.
 */
final class PemeriksaPromoPenjualan
{
    public function __construct(
        private readonly PromoBerlaku $promo,
        private readonly MesinPromo $mesin = new MesinPromo,
    ) {}

    /**
     * @param  array<string, DataProdukPenjualan>  $produk
     * @param  list<PromoTerpakai>  $perangkat
     * @return list<string>
     */
    public function Periksa(DataPenjualanPos $data, DataKalkulasi $dasar, array $produk, DataOutletPenjualan $outlet, ?string $kodeTier, array $perangkat): array
    {
        $definisi = $this->promo->AmbilDefinisi();

        if ($definisi === [] && $perangkat === []) {
            return [];
        }

        $server = $this->mesin->Terapkan(
            $dasar,
            array_values(array_map(fn (DataBarisPenjualanPos $b): BarisPromo => new BarisPromo($b->uuidProduk, $produk[$b->uuidProduk]->uuidKategori ?? null), $data->baris)),
            $definisi,
            new KonteksPromo($data->dibuatPada->utc(), $data->dibuatPada->setTimezone($outlet->zonaWaktu), $outlet->uuidOutlet, $data->kanal, $kodeTier),
            $this->promo->AmbilMode(),
        )->terpakai;

        $petaServer = self::Petakan($server);
        $petaPerangkat = self::Petakan($perangkat);

        if ($petaServer === $petaPerangkat) {
            return [];
        }

        $kode = fn (array $daftar): string => $daftar === [] ? 'tanpa promo' : implode(', ', array_map(fn (PromoTerpakai $p): string => "{$p->kode} {$p->HitungTotal()->FormatRupiah()}", $daftar));

        return ["perangkat: {$kode($perangkat)}; server: {$kode($server)}"];
    }

    /**
     * @param  list<PromoTerpakai>  $daftar
     * @return array<string, array{Baris: array<int, string>, Pesanan: string}>
     */
    private static function Petakan(array $daftar): array
    {
        $hasil = [];

        foreach ($daftar as $p) {
            $baris = array_map(fn ($u): string => $u->KeString(), $p->diskonBaris);
            ksort($baris);
            $hasil[$p->uuid] = ['Baris' => $baris, 'Pesanan' => $p->diskonPesanan->KeString()];
        }

        ksort($hasil);

        return $hasil;
    }
}
