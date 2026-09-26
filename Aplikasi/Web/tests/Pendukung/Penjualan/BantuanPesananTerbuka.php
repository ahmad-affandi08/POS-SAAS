<?php

declare(strict_types=1);

namespace Tests\Pendukung\Penjualan;

use App\Domain\Katalog\Model\Produk;
use App\Domain\Organisasi\Model\Meja;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\StasiunDapur;
use Carbon\CarbonImmutable;
use Tests\Pendukung\Kasir\BantuanKasir;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\TestCase;

/**
 * Pembuat item outbox `PesananTerbuka.*` (F-07 mode meja fase 1) untuk test. `$k` = hasil `BantuanPenjualan::Siapkan()`.
 * Waktu bawaan beberapa menit lalu agar urutan last-writer-wins bisa diatur lewat parameter waktu.
 */
final class BantuanPesananTerbuka
{
    private static int $urutan = 0;

    /**
     * @param  array<string, mixed>  $k
     * @return array{Jenis: string, Uuid: string, Data: array<string, mixed>}
     */
    public static function ItemBuka(array $k, ?Meja $meja = null, ?string $uuid = null, ?Pengguna $pengguna = null, ?string $label = null, int $tamu = 4): array
    {
        self::$urutan++;
        $perangkat = $k['Perangkat'];
        $outlet = $k['Outlet'];

        return [
            'Jenis' => 'PesananTerbuka.Buka',
            'Uuid' => $uuid ?? BantuanKasir::Uuid(),
            'Data' => [
                'Nomor' => "OB/{$outlet->Kode}/".CarbonImmutable::now()->format('ymd')."/{$perangkat->Kode}-".str_pad((string) self::$urutan, 4, '0', STR_PAD_LEFT),
                'UuidMeja' => $meja?->Uuid,
                'Label' => $label,
                'JumlahTamu' => $tamu,
                'UuidPengguna' => ($pengguna ?? $k['Kasir'])->Uuid,
                'DibukaPada' => CarbonImmutable::now()->subMinutes(20)->utc()->toIso8601ZuluString(),
            ],
        ];
    }

    /**
     * Baris: `[Produk, Jumlah, Harga, Catatan?, Uuid?]`.
     *
     * @param  array<string, mixed>  $k
     * @param  list<array{0: Produk, 1: string, 2: string, 3?: string|null, 4?: string}>  $baris
     * @return array{Jenis: string, Uuid: string, Data: array<string, mixed>}
     */
    public static function ItemTambah(array $k, string $uuidPesanan, array $baris, int $ronde = 1, bool $kirimDapur = true, ?Pengguna $pengguna = null): array
    {
        return [
            'Jenis' => 'PesananTerbuka.Tambah',
            'Uuid' => BantuanKasir::Uuid(),
            'Data' => [
                'UuidPesanan' => $uuidPesanan,
                'Ronde' => $ronde,
                'KirimDapur' => $kirimDapur,
                'UuidPengguna' => ($pengguna ?? $k['Kasir'])->Uuid,
                'DikirimPada' => CarbonImmutable::now()->subMinutes(15)->utc()->toIso8601ZuluString(),
                'Baris' => array_map(fn (array $b): array => [
                    'Uuid' => $b[4] ?? BantuanKasir::Uuid(),
                    'UuidProduk' => $b[0]->Uuid,
                    'UuidProdukSatuan' => null,
                    'Jumlah' => $b[1],
                    'HargaSatuan' => $b[2],
                    'HargaPilihan' => '0.00',
                    'Pilihan' => [],
                    'Catatan' => $b[3] ?? null,
                ], $baris),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $k
     * @param  array<string, mixed>  $data
     * @return array{Jenis: string, Uuid: string, Data: array<string, mixed>}
     */
    public static function Item(array $k, string $jenis, string $uuidPesanan, array $data, string $bidangWaktu, ?Pengguna $pengguna = null, ?CarbonImmutable $waktu = null): array
    {
        return [
            'Jenis' => "PesananTerbuka.{$jenis}",
            'Uuid' => BantuanKasir::Uuid(),
            'Data' => [
                'UuidPesanan' => $uuidPesanan,
                'UuidPengguna' => ($pengguna ?? $k['Kasir'])->Uuid,
                $bidangWaktu => ($waktu ?? CarbonImmutable::now()->subMinutes(10))->utc()->toIso8601ZuluString(),
                ...$data,
            ],
        ];
    }

    /**
     * Tenant kasir + meja 7 & 9, stasiun Bar & Dapur, kopi (kategori Minuman → Bar) dan nasi goreng (kategori Makanan →
     * Dapur).
     *
     * @return array<string, mixed>
     */
    public static function SiapkanRestoran(TestCase $tes): array
    {
        $k = BantuanPenjualan::Siapkan($tes, 'Kedai Kopi Senja Rasa Nusantara');
        $bar = StasiunDapur::query()->create(['Nama' => 'Bar', 'Urutan' => 1]);
        $dapur = StasiunDapur::query()->create(['Nama' => 'Dapur', 'Urutan' => 2]);
        $minuman = BantuanKatalog::BuatKategori('Minuman');
        $minuman->forceFill(['IdStasiunDapur' => $bar->Id])->save();
        $makanan = BantuanKatalog::BuatKategori('Makanan');
        $makanan->forceFill(['IdStasiunDapur' => $dapur->Id])->save();
        $kopi = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id, 'Es Kopi Susu Gula Aren Ukuran Besar', '50', '8000', '25000.00');
        $kopi->forceFill(['IdKategori' => $minuman->Id])->save();
        $nasi = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id, 'Nasi Goreng Kampung Spesial Telur Mata Sapi', '50', '12000', '35000.00');
        $nasi->forceFill(['IdKategori' => $makanan->Id])->save();
        $meja = Meja::query()->create(['IdOutlet' => $k['Outlet']->Id, 'Nama' => '7', 'Kapasitas' => 4]);
        $meja9 = Meja::query()->create(['IdOutlet' => $k['Outlet']->Id, 'Nama' => '9', 'Kapasitas' => 2]);

        return $k + ['Minuman' => $minuman, 'Makanan' => $makanan, 'Bar' => $bar, 'Dapur' => $dapur, 'Kopi' => $kopi, 'Nasi' => $nasi, 'Meja' => $meja, 'Meja9' => $meja9];
    }

    /**
     * Kirim item outbox lalu pulihkan konteks tenant untuk pemeriksaan berikutnya.
     *
     * @param  array<string, mixed>  $k
     * @param  list<array{Jenis: string, Uuid: string, Data: array<string, mixed>}>  $item
     * @return list<array{0: string, 1: string|null}>
     */
    public static function Kirim(TestCase $tes, array $k, array $item, ?string $token = null): array
    {
        $hasil = BantuanKasir::KirimRingkas($tes, $token ?? $k['Token'], $item);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);

        return $hasil;
    }
}
