<?php

declare(strict_types=1);

use App\Domain\Katalog\Data\KonteksKatalogPos;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Kueri\ProdukUntukPos;
use App\Domain\Katalog\Model\Produk;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * QA F-03 katalog POS delta (D.3): bagian Produk Tim 1 hanya memuat Uuid produk yang dirujuk (induk varian, pemilik
 * satuan/barcode), tidak memuat seluruh katalog tenant di setiap sinkron delta; rujukan ke induk yang tidak
 * berubah tetap terisi.
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('delta: anak varian berubah → UuidInduk terisi walau induk tidak berubah; Uuid produk dimuat terbatas', function (): void {
    $t = BantuanKatalog::SiapkanTenantProduk();
    Carbon::setTestNow('2026-09-27 01:00:00');
    $induk = BantuanKatalog::BuatProduk(['Jenis' => JenisProduk::IndukVarian, 'Sku' => 'KAOS', 'AtributVarian' => [['Nama' => 'Ukuran', 'Nilai' => ['S', 'M']]]], null, $t['Pcs']);
    $anak = BantuanKatalog::BuatProduk(['IdInduk' => $induk->Id, 'KunciVarian' => 'ukuran=s', 'AtributVarian' => [['Nama' => 'Ukuran', 'Nilai' => 'S']]], null, $t['Pcs']);

    foreach (range(1, 5) as $nomor) {
        BantuanKatalog::BuatProduk(['Nama' => "Sabun Batang Wangi Melati 90 gram #{$nomor}"], null, $t['Pcs']);
    }

    Carbon::setTestNow('2026-09-27 03:00:00');
    Produk::query()->whereKey($anak->Id)->update(['Nama' => 'Kaos Polos Katun Combed 30s Ukuran S', 'DiubahPada' => now()]);

    $sql = [];
    DB::listen(function (QueryExecuted $kueri) use (&$sql): void {
        $sql[] = $kueri->sql;
    });
    $bagian = app(ProdukUntukPos::class)->AmbilBagian(new KonteksKatalogPos($t['Tenant']->Id, $t['Outlet']->Id, CarbonImmutable::parse('2026-09-27 02:00:00')));

    expect(array_column($bagian['Produk'], 'Uuid'))->toBe([$anak->Uuid])
        ->and($bagian['Produk'][0]['UuidInduk'])->toBe($induk->Uuid);

    $muatSemuaUuid = array_filter($sql, fn (string $isi): bool => str_contains($isi, 'select `Uuid`, `Id` from `Produk`') && ! str_contains($isi, '`Id` in'));
    expect($muatSemuaUuid)->toBe([]);
});
