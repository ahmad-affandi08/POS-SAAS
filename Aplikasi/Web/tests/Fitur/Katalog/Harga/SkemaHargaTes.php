<?php

declare(strict_types=1);

use App\Domain\Katalog\Harga\Enum\SumberPerubahanHarga;
use App\Domain\Katalog\Harga\Model\DaftarHarga;
use App\Domain\Katalog\Harga\Model\RiwayatHarga;
use App\Domain\Katalog\Model\ProdukHarga;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;
use Tests\Pendukung\Katalog\BantuanHarga;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

describe('F-03 skema harga & pajak (Tim 2)', function (): void {
    it('membuat DaftarHarga, RiwayatHarga, relasi ProdukHarga, dan KelompokPajak.Kategori dengan indeks diawali IdTenant', function (): void {
        expect(Schema::hasTable('DaftarHarga'))->toBeTrue()
            ->and(Schema::hasTable('RiwayatHarga'))->toBeTrue()
            ->and(Schema::hasColumn('ProdukHarga', 'KunciDaftarHarga'))->toBeTrue()
            ->and(Schema::hasColumn('KelompokPajak', 'Kategori'))->toBeTrue();

        // Schema::getIndexes() mengembalikan nama indeks MySQL dalam huruf kecil.
        $indeks = fn (string $tabel): array => array_column(Schema::getIndexes($tabel), 'columns', 'name');
        $kecil = fn (array $harapan): array => array_change_key_case($harapan, CASE_LOWER);
        expect($indeks('DaftarHarga'))->toMatchArray($kecil([
            'UniqDaftarHargaIdTenantNama' => ['IdTenant', 'Nama'],
            'IdxDaftarHargaIdTenantAktifPrioritas' => ['IdTenant', 'Aktif', 'Prioritas'],
            'IdxDaftarHargaIdTenantDiubahPada' => ['IdTenant', 'DiubahPada'],
        ]))
            ->and($indeks('ProdukHarga'))->toMatchArray($kecil([
                'UniqProdukHargaIdTenantIdProdukSatuanKunciJumlah' => ['IdTenant', 'IdProdukSatuan', 'KunciDaftarHarga', 'JumlahMinimum'],
                'IdxProdukHargaIdTenantIdDaftarHarga' => ['IdTenant', 'IdDaftarHarga'],
                'IdxProdukHargaIdTenantDiubahPada' => ['IdTenant', 'DiubahPada'],
            ]))
            ->and($indeks('RiwayatHarga'))->toMatchArray($kecil(['IdxRiwayatHargaIdTenantIdProdukDibuatPada' => ['IdTenant', 'IdProduk', 'DibuatPada']]));
    });

    it('indeks unik menolak baris harga dasar ganda walau IdDaftarHarga NULL (kirim ganda aman)', function (): void {
        BantuanKatalog::BuatTenant();
        $produk = BantuanKatalog::BuatProduk(harga: '5000.00');
        $satuan = BantuanHarga::SatuanDasar($produk);
        $daftar = BantuanHarga::BuatDaftarHarga();
        BantuanHarga::TambahHargaDaftar($daftar, $satuan, '1', '4500');

        expect(ProdukHarga::query()->where('IdDaftarHarga', $daftar->Id)->value('KunciDaftarHarga'))->toBe($daftar->Id)
            ->and(fn () => ProdukHarga::query()->create(['IdProduk' => $produk->Id, 'IdProdukSatuan' => $satuan->Id, 'JumlahMinimum' => '1.0000', 'Harga' => '4000']))
            ->toThrow(UniqueConstraintViolationException::class);
    });

    it('DaftarHarga: IdOutlet json, Kanal enum, dan FK dari ProdukHarga menolak daftar harga yang tidak ada', function (): void {
        ['Outlet' => $outlet] = BantuanKatalog::BuatTenant();
        $daftar = BantuanHarga::BuatDaftarHarga('Harga Ojek Online', ['IdOutlet' => [$outlet->Id], 'Kanal' => KanalPenjualan::Online, 'Prioritas' => 10]);
        $daftar->refresh();

        expect($daftar->IdOutlet)->toBe([$outlet->Id])
            ->and($daftar->Kanal)->toBe(KanalPenjualan::Online)
            ->and($daftar->Aktif)->toBeTrue();

        $produk = BantuanKatalog::BuatProduk();
        expect(fn () => ProdukHarga::query()->create([
            'IdProduk' => $produk->Id,
            'IdProdukSatuan' => BantuanHarga::SatuanDasar($produk)->Id,
            'IdDaftarHarga' => 999999,
            'JumlahMinimum' => '1',
            'Harga' => '1000',
        ]))->toThrow(QueryException::class);
    });

    it('nama DaftarHarga unik per tenant, boleh sama di tenant lain', function (): void {
        ['Tenant' => $tenantA] = BantuanKatalog::BuatTenant();
        BantuanHarga::BuatDaftarHarga('Harga Member');
        expect(fn () => BantuanHarga::BuatDaftarHarga('Harga Member'))->toThrow(UniqueConstraintViolationException::class);

        BantuanKatalog::BuatTenant('Toko Berkah Abadi');
        BantuanHarga::BuatDaftarHarga('Harga Member');
        expect(DaftarHarga::query()->count())->toBe(1);

        BantuanOrganisasi::AturKonteks($tenantA->Id);
        expect(DaftarHarga::query()->count())->toBe(1);
    });

    it('BR-03.3 RiwayatHarga append-only: tidak bisa diubah atau dihapus', function (): void {
        BantuanKatalog::BuatTenant();
        $produk = BantuanKatalog::BuatProduk();
        $riwayat = RiwayatHarga::query()->create([
            'IdProduk' => $produk->Id,
            'IdProdukSatuan' => BantuanHarga::SatuanDasar($produk)->Id,
            'IdSatuan' => $produk->IdSatuanDasar,
            'JumlahMinimum' => '1',
            'HargaLama' => null,
            'HargaBaru' => '18000',
            'Sumber' => SumberPerubahanHarga::Manual,
        ]);

        expect(fn () => $riwayat->fill(['HargaBaru' => '1'])->save())->toThrow(LogicException::class)
            ->and(fn () => $riwayat->delete())->toThrow(LogicException::class);
    });
});
