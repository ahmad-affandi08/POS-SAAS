<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

describe('F-03 Tim 3: skema komposisi', function (): void {
    it('tabel komposisi punya indeks unik dan indeks delta yang diawali IdTenant', function (): void {
        // Schema::getIndexes() mengembalikan nama indeks MySQL dalam huruf kecil.
        $indeks = fn (string $tabel): array => array_column(Schema::getIndexes($tabel), 'columns', 'name');
        $kecil = fn (array $harapan): array => array_change_key_case($harapan, CASE_LOWER);

        expect($indeks('KelompokPilihan'))->toMatchArray($kecil([
            'UniqKelompokPilihanIdTenantNama' => ['IdTenant', 'Nama'],
            'IdxKelompokPilihanIdTenantDiubahPada' => ['IdTenant', 'DiubahPada'],
        ]))
            ->and($indeks('Pilihan'))->toMatchArray($kecil([
                'UniqPilihanIdTenantIdKelompokPilihanNama' => ['IdTenant', 'IdKelompokPilihan', 'Nama'],
                'IdxPilihanIdTenantDiubahPada' => ['IdTenant', 'DiubahPada'],
            ]))
            ->and($indeks('ProdukKelompokPilihan'))->toMatchArray($kecil([
                'UniqProdukKelompokPilihanIdTenantIdProdukIdKelompokPilihan' => ['IdTenant', 'IdProduk', 'IdKelompokPilihan'],
            ]))
            ->and($indeks('Resep'))->toMatchArray($kecil([
                'UniqResepIdTenantIdProdukVersi' => ['IdTenant', 'IdProduk', 'Versi'],
                'IdxResepIdTenantDiubahPada' => ['IdTenant', 'DiubahPada'],
            ]))
            ->and($indeks('ResepDetail'))->toMatchArray($kecil([
                'IdxResepDetailIdTenantIdResep' => ['IdTenant', 'IdResep'],
                'IdxResepDetailIdTenantIdProdukBahan' => ['IdTenant', 'IdProdukBahan'],
            ]))
            ->and($indeks('PaketProdukDetail'))->toMatchArray($kecil([
                'UniqPaketProdukDetailIdTenantIdProdukPaketIdProdukKomponen' => ['IdTenant', 'IdProdukPaket', 'IdProdukKomponen'],
                'IdxPaketProdukDetailIdTenantDiubahPada' => ['IdTenant', 'DiubahPada'],
            ]));

        $tipe = fn (string $tabel, string $kolom): string => (string) collect(Schema::getColumns($tabel))->firstWhere('name', $kolom)['type'];
        expect($tipe('Pilihan', 'Harga'))->toBe('decimal(18,2)')
            ->and($tipe('Pilihan', 'Jumlah'))->toBe('decimal(18,4)')
            ->and($tipe('ResepDetail', 'JumlahDasar'))->toBe('decimal(18,4)')
            ->and($tipe('ResepDetail', 'PersenSusut'))->toBe('decimal(9,6)')
            ->and($tipe('PaketProdukDetail', 'AlokasiHarga'))->toBe('decimal(9,6)');
    });
});
