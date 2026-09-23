<?php

declare(strict_types=1);

use App\Domain\Katalog\Enum\EntitasKatalog;
use App\Domain\Katalog\Enum\JenisNomorUrutKatalog;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Enum\StatusProduk;
use App\Domain\Katalog\Kontrak\PemeriksaPemakaianProduk;
use App\Domain\Katalog\Kueri\KepalaProduk;
use App\Domain\Katalog\Layanan\PencatatPenghapusanKatalog;
use App\Domain\Katalog\Layanan\PenilaiPemakaianProduk;
use App\Domain\Katalog\Model\NomorUrutKatalog;
use App\Domain\Katalog\Model\PenghapusanKatalog;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\ProdukBarcode;
use App\Domain\Katalog\Model\ProdukGudang;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Organisasi\Model\Gudang;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

describe('F-03 Wave 0: skema katalog', function (): void {
    it('menambah kolom katalog ke Produk dan Uuid wajib unik ke ProdukSatuan', function (): void {
        expect(Schema::hasColumns('Produk', ['Merek', 'IdInduk', 'AtributVarian', 'KunciVarian', 'HargaTermasukPajak', 'PathGambar', 'DiarsipkanPada', 'DihapusPada']))->toBeTrue()
            ->and(Schema::hasColumn('ProdukSatuan', 'Uuid'))->toBeTrue()
            ->and(Schema::hasTable('ProdukBarcode'))->toBeTrue()
            ->and(Schema::hasTable('ProdukGudang'))->toBeTrue()
            ->and(Schema::hasTable('NomorUrutKatalog'))->toBeTrue()
            ->and(Schema::hasTable('PenghapusanKatalog'))->toBeTrue();

        // Schema::getIndexes() mengembalikan nama indeks MySQL dalam huruf kecil.
        $indeks = fn (string $tabel): array => array_column(Schema::getIndexes($tabel), 'columns', 'name');
        $kecil = fn (array $harapan): array => array_change_key_case($harapan, CASE_LOWER);
        expect($indeks('Produk'))->toMatchArray($kecil([
            'UniqProdukIdTenantIdIndukKunciVarian' => ['IdTenant', 'IdInduk', 'KunciVarian'],
            'IdxProdukIdTenantDiubahPada' => ['IdTenant', 'DiubahPada'],
            'IdxProdukIdTenantJenisDiarsipkanPada' => ['IdTenant', 'Jenis', 'DiarsipkanPada'],
        ]))
            ->and($indeks('ProdukSatuan'))->toMatchArray($kecil(['UniqProdukSatuanUuid' => ['Uuid'], 'IdxProdukSatuanIdTenantDiubahPada' => ['IdTenant', 'DiubahPada']]))
            ->and($indeks('Kategori'))->toHaveKey('idxkategoriidtenantdiubahpada')
            ->and($indeks('Satuan'))->toHaveKey('idxsatuanidtenantdiubahpada')
            ->and($indeks('ProdukBarcode'))->toMatchArray($kecil(['UniqProdukBarcodeIdTenantBarcode' => ['IdTenant', 'Barcode']]))
            ->and($indeks('PenghapusanKatalog'))->toMatchArray($kecil(['IdxPenghapusanKatalogIdTenantDihapusPada' => ['IdTenant', 'DihapusPada']]));

        $kolomSatuanUuid = collect(Schema::getColumns('ProdukSatuan'))->firstWhere('name', 'Uuid');
        expect($kolomSatuanUuid['nullable'] ?? null)->toBeFalse();
    });

    it('ProdukSatuan baru mendapat Uuid ULID otomatis', function (): void {
        BantuanKatalog::BuatTenant();
        $produk = BantuanKatalog::BuatProduk();

        expect(ProdukSatuan::query()->where('IdProduk', $produk->Id)->sole()->Uuid)->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
    });
});

describe('F-03 Wave 0: model katalog', function (): void {
    it('BR-03.2 produk diarsipkan berstatus Diarsipkan dan soft delete menyembunyikan produk', function (): void {
        BantuanKatalog::BuatTenant();
        $produk = BantuanKatalog::BuatProduk();
        expect($produk->AmbilStatus())->toBe(StatusProduk::Aktif);

        $produk->fill(['Aktif' => false, 'DiarsipkanPada' => now()])->save();
        expect($produk->refresh()->AmbilStatus())->toBe(StatusProduk::Diarsipkan);

        $produk->delete();
        expect(Produk::query()->whereKey($produk->Id)->exists())->toBeFalse()
            ->and(Produk::query()->withTrashed()->whereKey($produk->Id)->sole()->DihapusPada)->not->toBeNull();
    });

    it('varian: relasi Induk/Anak, AtributVarian array, dan KunciVarian unik per induk', function (): void {
        BantuanKatalog::BuatTenant();
        $induk = BantuanKatalog::BuatProduk(['Jenis' => JenisProduk::IndukVarian, 'Nama' => 'Kaos Polos Katun Combed 30s', 'AtributVarian' => [['Nama' => 'Ukuran', 'Nilai' => ['S', 'M']]]], harga: null);
        $anak = BantuanKatalog::BuatProduk(['IdInduk' => $induk->Id, 'AtributVarian' => [['Nama' => 'Ukuran', 'Nilai' => 'M']], 'KunciVarian' => 'ukuran=m']);

        $induk = Produk::query()->with(['Anak', 'Satuan', 'SatuanDasar'])->findOrFail($induk->Id);
        expect($induk->AtributVarian)->toBe([['Nama' => 'Ukuran', 'Nilai' => ['S', 'M']]])
            ->and($induk->Anak->pluck('Id')->all())->toBe([$anak->Id])
            ->and($induk->Satuan)->toHaveCount(1)
            ->and($induk->SatuanDasar->Simbol)->toBe('pcs')
            ->and(Produk::query()->with('Induk')->findOrFail($anak->Id)->Induk?->Id)->toBe($induk->Id);

        expect(fn () => BantuanKatalog::BuatProduk(['IdInduk' => $induk->Id, 'KunciVarian' => 'ukuran=m']))
            ->toThrow(UniqueConstraintViolationException::class);
    });

    it('BR-03.1 barcode unik per tenant tanpa beda huruf besar/kecil, boleh sama di tenant lain', function (): void {
        ['Tenant' => $tenantA] = BantuanKatalog::BuatTenant('Toko Sumber Rejeki');
        $produkA = BantuanKatalog::BuatProduk();
        $satuanA = ProdukSatuan::query()->where('IdProduk', $produkA->Id)->sole();
        ProdukBarcode::query()->create(['IdProduk' => $produkA->Id, 'IdProdukSatuan' => $satuanA->Id, 'Barcode' => 'ABC-8991234567890']);

        expect(fn () => ProdukBarcode::query()->create(['IdProduk' => $produkA->Id, 'IdProdukSatuan' => $satuanA->Id, 'Barcode' => 'abc-8991234567890']))
            ->toThrow(UniqueConstraintViolationException::class);

        ['Tenant' => $tenantB] = BantuanKatalog::BuatTenant('Toko Makmur Jaya');
        $produkB = BantuanKatalog::BuatProduk();
        $satuanB = ProdukSatuan::query()->where('IdProduk', $produkB->Id)->sole();
        $barcodeB = ProdukBarcode::query()->create(['IdProduk' => $produkB->Id, 'IdProdukSatuan' => $satuanB->Id, 'Barcode' => 'ABC-8991234567890']);

        expect($barcodeB->IdTenant)->toBe($tenantB->Id)
            ->and(ProdukBarcode::query()->count())->toBe(1)
            ->and(Produk::query()->with('Barcode')->findOrFail($produkB->Id)->Barcode->pluck('Barcode')->all())->toBe(['ABC-8991234567890']);

        BantuanOrganisasi::AturKonteks($tenantA->Id);
        expect(ProdukBarcode::query()->sole()->IdProduk)->toBe($produkA->Id);
    });

    it('ProdukGudang menyimpan batas stok sebagai string desimal 4 angka', function (): void {
        BantuanKatalog::BuatTenant();
        $produk = BantuanKatalog::BuatProduk();
        $gudang = Gudang::query()->orderBy('Id')->firstOrFail();

        $batas = ProdukGudang::query()->create(['IdProduk' => $produk->Id, 'IdGudang' => $gudang->Id, 'StokMinimum' => '2.5', 'StokMaksimum' => null]);

        expect($batas->refresh()->StokMinimum)->toBe('2.5000')
            ->and($batas->StokMaksimum)->toBeNull();
    });
});

describe('F-03 Wave 0: migrasi 000107 IsiSkuProdukLama (BR-03.1)', function (): void {
    it('memberi SKU PRD-nnnnnn urut Id per tenant, melewati SKU terpakai, dan mengisi NomorUrutKatalog', function (): void {
        ['Tenant' => $tenantA] = BantuanKatalog::BuatTenant('Toko Sumber Rejeki');
        $a1 = BantuanKatalog::BuatProduk();
        $a2 = BantuanKatalog::BuatProduk(['Sku' => 'prd-000002']);
        $a3 = BantuanKatalog::BuatProduk();
        $a4 = BantuanKatalog::BuatProduk();
        ['Tenant' => $tenantB] = BantuanKatalog::BuatTenant('Toko Makmur Jaya');
        $b1 = BantuanKatalog::BuatProduk();
        DB::table('Produk')->whereIn('Id', [$a1->Id, $a3->Id, $a4->Id, $b1->Id])->update(['Sku' => null]);

        (require database_path('migrations/2026_09_27_000107_IsiSkuProdukLama.php'))->up();

        $sku = DB::table('Produk')->orderBy('Id')->pluck('Sku', 'Id')->all();
        expect($sku[$a1->Id])->toBe('PRD-000001')
            ->and($sku[$a2->Id])->toBe('prd-000002')
            ->and($sku[$a3->Id])->toBe('PRD-000003')
            ->and($sku[$a4->Id])->toBe('PRD-000004')
            ->and($sku[$b1->Id])->toBe('PRD-000001');

        BantuanOrganisasi::AturKonteks($tenantA->Id);
        $nomor = NomorUrutKatalog::query()->sole();
        expect($nomor->Jenis)->toBe(JenisNomorUrutKatalog::Sku)->and($nomor->NomorTerakhir)->toBe(4);
        BantuanOrganisasi::AturKonteks($tenantB->Id);
        expect(NomorUrutKatalog::query()->sole()->NomorTerakhir)->toBe(1);
    });
});

describe('F-03 Wave 0: layanan dan kueri katalog', function (): void {
    it('BR-03.2 PenilaiPemakaianProduk mengembalikan alasan pertama dari pemeriksa bertag, atau null', function (): void {
        BantuanKatalog::BuatTenant();
        $produk = BantuanKatalog::BuatProduk();
        $penilai = app(PenilaiPemakaianProduk::class);
        expect($penilai->AmbilAlasan($produk))->toBeNull();

        app()->instance('uji.pemeriksa.kosong', new class implements PemeriksaPemakaianProduk
        {
            public function PeriksaPemakaian(int $idProduk): ?string
            {
                return null;
            }
        });
        app()->instance('uji.pemeriksa.penjualan', new class implements PemeriksaPemakaianProduk
        {
            public function PeriksaPemakaian(int $idProduk): ?string
            {
                return 'Sudah ada penjualan';
            }
        });
        app()->tag(['uji.pemeriksa.kosong', 'uji.pemeriksa.penjualan'], PemeriksaPemakaianProduk::TAG);

        expect(app(PenilaiPemakaianProduk::class)->AmbilAlasan($produk))->toBe('Sudah ada penjualan');
    });

    it('PencatatPenghapusanKatalog mencatat jejak di tenant aktif saja', function (): void {
        ['Tenant' => $tenantA] = BantuanKatalog::BuatTenant('Toko Sumber Rejeki');
        app(PencatatPenghapusanKatalog::class)->Catat(EntitasKatalog::ProdukBarcode, '01JABCDEFGHJKMNPQRSTVWXYZ0');

        $jejak = PenghapusanKatalog::query()->sole();
        expect($jejak->IdTenant)->toBe($tenantA->Id)
            ->and($jejak->Entitas)->toBe(EntitasKatalog::ProdukBarcode)
            ->and($jejak->UuidEntitas)->toBe('01JABCDEFGHJKMNPQRSTVWXYZ0')
            ->and($jejak->DihapusPada)->not->toBeNull();

        BantuanKatalog::BuatTenant('Toko Makmur Jaya');
        expect(PenghapusanKatalog::query()->count())->toBe(0);
    });

    it('KepalaProduk menyusun tab menurut jenis produk', function (): void {
        BantuanKatalog::BuatTenant();
        $kepala = app(KepalaProduk::class);
        $tab = fn (Produk $produk): array => array_column($kepala->Ambil($produk)['Tab'], 'Kunci');

        expect($tab(BantuanKatalog::BuatProduk(['Jenis' => JenisProduk::Stok])))->toBe(['Ringkasan', 'Harga', 'Pilihan'])
            ->and($tab(BantuanKatalog::BuatProduk(['Jenis' => JenisProduk::Resep])))->toBe(['Ringkasan', 'Harga', 'Pilihan', 'Resep'])
            ->and($tab(BantuanKatalog::BuatProduk(['Jenis' => JenisProduk::Produksi])))->toBe(['Ringkasan', 'Harga', 'Pilihan', 'Resep'])
            ->and($tab(BantuanKatalog::BuatProduk(['Jenis' => JenisProduk::Paket])))->toBe(['Ringkasan', 'Harga', 'Pilihan', 'Komponen'])
            ->and($tab(BantuanKatalog::BuatProduk(['Jenis' => JenisProduk::BahanBaku], harga: null)))->toBe(['Ringkasan'])
            ->and($tab(BantuanKatalog::BuatProduk(['Jenis' => JenisProduk::IndukVarian], harga: null)))->toBe(['Ringkasan', 'Pilihan']);
    });

    it('KepalaProduk anak varian: tanpa tab Pilihan, dengan induk, status, dan URL gambar kecil berversi', function (): void {
        BantuanKatalog::BuatTenant();
        $induk = BantuanKatalog::BuatProduk(['Jenis' => JenisProduk::IndukVarian, 'Nama' => 'Kemeja Batik Parang Lengan Panjang'], harga: null);
        $anak = BantuanKatalog::BuatProduk([
            'IdInduk' => $induk->Id,
            'Nama' => 'Kemeja Batik Parang Lengan Panjang L',
            'Sku' => 'BTK-PRG-01',
            'PathGambar' => "produk/1/{$induk->Uuid}-01JBGAMBARVERSI0000000000A.webp",
            'Aktif' => false,
            'DiarsipkanPada' => now(),
        ]);

        $hasil = app(KepalaProduk::class)->Ambil($anak);

        expect($hasil)->toMatchArray([
            'Uuid' => $anak->Uuid,
            'Nama' => 'Kemeja Batik Parang Lengan Panjang L',
            'Sku' => 'BTK-PRG-01',
            'Jenis' => 'Stok',
            'LabelJenis' => 'Barang stok',
            'Status' => 'Diarsipkan',
            'UrlGambarKecil' => "/kelola/produk/{$anak->Uuid}/gambar?ukuran=kecil&versi=01JBGAMBARVERSI0000000000A",
            'UuidInduk' => $induk->Uuid,
            'NamaInduk' => 'Kemeja Batik Parang Lengan Panjang',
        ])
            ->and($hasil['Tab'])->toBe([
                ['Kunci' => 'Ringkasan', 'Label' => 'Ringkasan', 'Tautan' => "/kelola/produk/{$anak->Uuid}"],
                ['Kunci' => 'Harga', 'Label' => 'Harga', 'Tautan' => "/kelola/produk/{$anak->Uuid}/harga"],
            ]);
    });

    it('config katalog memuat semua kunci Wave 0', function (): void {
        expect(config('katalog.Sku.Awalan'))->toBe('PRD-')
            ->and(config('katalog.Barcode.Awalan'))->toBe('20')
            ->and(config('katalog.Gambar'))->toBe(['UkuranMaksimalKb' => 5120, 'SisiBesar' => 800, 'SisiKecil' => 256, 'Kualitas' => 80])
            ->and(config('katalog.Varian'))->toBe(['MaksimalAtribut' => 3, 'MaksimalNilai' => 20, 'MaksimalKombinasi' => 100])
            ->and(config('katalog.Kategori.MaksimalKedalaman'))->toBe(3)
            ->and(config('katalog.Impor.BatasBarisSinkron'))->toBe(300)
            ->and(config('katalog.Pos'))->toBe(['TumpangTindihDetik' => 120, 'UmurKursorMaksimalHari' => 90]);
    });
});
