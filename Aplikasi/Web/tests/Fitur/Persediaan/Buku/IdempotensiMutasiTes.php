<?php

declare(strict_types=1);

use App\Domain\Persediaan\Enum\JenisMutasi;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Model\MutasiStok;
use Tests\Pendukung\Persediaan\BantuanBuku;
use Tests\Pendukung\Persediaan\BantuanPersediaan;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * Idempotensi buku stok per (JenisReferensi, IdReferensi, KunciBaris) (aturan #13, DesainF05a C.2 langkah 4):
 * dokumen yang sama dikirim ulang (misal sinkron POS diulang) = baris yang sama, `sudahAda`, saldo tidak berubah.
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

describe('F-05a buku stok: idempotensi (BR-05.1, aturan #13)', function (): void {
    it('dokumen yang sama dicatat dua kali → Id baris sama, sudahAda, tanpa baris & perubahan saldo baru', function (): void {
        $t = BantuanPersediaan::SiapkanTenant();
        $p = BantuanPersediaan::BuatProdukSemuaJenis($t['Pcs'], $t['Kg']);
        $g = $t['Gudang']->Id;
        $baris = [
            BantuanBuku::BuatBaris('P/1', $p['Stok']->Id, $g, '24', '924000.00', hppSatuan: '38500'),
            BantuanBuku::BuatBaris('P/2', $p['BahanBaku']->Id, $g, '12.5', '178125.00'),
        ];

        $pertama = BantuanBuku::Catat($baris, JenisReferensiMutasi::StokAwal, 3101);
        $saldoSetelahPertama = BantuanBuku::AmbilSaldo($p['Stok']->Id, $g)?->only(['JumlahTersedia', 'NilaiPersediaan', 'HppRataRata', 'IdMutasiStokTerakhir']);
        $kedua = BantuanBuku::Catat($baris, JenisReferensiMutasi::StokAwal, 3101);

        expect($pertama->sudahAda)->toBeFalse()
            ->and($kedua->sudahAda)->toBeTrue()
            ->and(array_keys($kedua->baris))->toBe(['P/1', 'P/2'])
            ->and($kedua->baris['P/1']->idMutasiStok)->toBe($pertama->baris['P/1']->idMutasiStok)
            ->and($kedua->baris['P/2']->idMutasiStok)->toBe($pertama->baris['P/2']->idMutasiStok)
            ->and($kedua->TotalHpp()->KeString())->toBe($pertama->TotalHpp()->KeString())
            ->and($kedua->TotalNilaiDiminta()->KeString())->toBe('1102125.00')
            ->and((string) $kedua->baris['P/1']->hppSatuan)->toBe('38500.000000')
            ->and($kedua->baris['P/2']->saldoSetelah->KeString())->toBe('12.5000')
            ->and(MutasiStok::query()->count())->toBe(2)
            ->and(BantuanBuku::AmbilSaldo($p['Stok']->Id, $g)?->only(['JumlahTersedia', 'NilaiPersediaan', 'HppRataRata', 'IdMutasiStokTerakhir']))->toBe($saldoSetelahPertama)
            ->and(BantuanBuku::PeriksaInvarianBuku($t['Tenant']->Id))->toBe([]);
    });

    it('pemutaran ulang tetap idempoten walau stok sudah berubah sesudahnya (tidak dinilai ulang)', function (): void {
        $t = BantuanPersediaan::SiapkanTenant();
        $p = BantuanPersediaan::BuatProdukSemuaJenis($t['Pcs'], $t['Kg']);
        $g = $t['Gudang']->Id;
        $jual = [BantuanBuku::BuatBaris('J/1', $p['Stok']->Id, $g, '-2', null, JenisMutasi::Penjualan)];

        BantuanBuku::CatatMasuk($p['Stok']->Id, $g, '10', '385000.00');
        $pertama = BantuanBuku::Catat($jual, JenisReferensiMutasi::Penjualan, 88);
        BantuanBuku::CatatKeluar($p['Stok']->Id, $g, '8');
        $ulang = BantuanBuku::Catat($jual, JenisReferensiMutasi::Penjualan, 88);

        expect($ulang->sudahAda)->toBeTrue()
            ->and($ulang->baris['J/1']->totalHpp->KeString())->toBe('-77000.00')
            ->and($ulang->baris['J/1']->idMutasiStok)->toBe($pertama->baris['J/1']->idMutasiStok)
            ->and(BantuanBuku::AmbilSaldo($p['Stok']->Id, $g)?->JumlahTersedia)->toBe('0.0000');
    });

    it('sebagian kunci baris sudah tercatat → MutasiGanda, tidak ada baris baru', function (): void {
        $t = BantuanPersediaan::SiapkanTenant();
        $p = BantuanPersediaan::BuatProdukSemuaJenis($t['Pcs'], $t['Kg']);
        $g = $t['Gudang']->Id;

        BantuanBuku::Catat([BantuanBuku::BuatBaris('P/1', $p['Stok']->Id, $g, '5', '192500.00')], JenisReferensiMutasi::StokAwal, 3102);
        $galat = BantuanBuku::TangkapPelanggaran(fn () => BantuanBuku::Catat([
            BantuanBuku::BuatBaris('P/1', $p['Stok']->Id, $g, '5', '192500.00'),
            BantuanBuku::BuatBaris('P/2', $p['Produksi']->Id, $g, '3', '75000.00'),
        ], JenisReferensiMutasi::StokAwal, 3102));

        expect($galat->kode)->toBe('MutasiGanda')
            ->and($galat->detail)->toBe(['KunciBarisSudahAda' => ['P/1']])
            ->and(MutasiStok::query()->count())->toBe(1)
            ->and(BantuanBuku::AmbilSaldo($p['Produksi']->Id, $g)->JumlahTersedia ?? '0.0000')->toBe('0.0000');
    });

    it('kunci baris sama pada dokumen atau jenis referensi berbeda = mutasi berbeda', function (): void {
        $t = BantuanPersediaan::SiapkanTenant();
        $p = BantuanPersediaan::BuatProdukSemuaJenis($t['Pcs'], $t['Kg']);
        $g = $t['Gudang']->Id;
        $baris = [BantuanBuku::BuatBaris('P/1', $p['Stok']->Id, $g, '5', '192500.00', JenisMutasi::PenyesuaianMasuk)];

        BantuanBuku::Catat($baris, JenisReferensiMutasi::PenyesuaianStok, 7);
        BantuanBuku::Catat($baris, JenisReferensiMutasi::PenyesuaianStok, 8);
        $lain = BantuanBuku::Catat([BantuanBuku::BuatBaris('P/1', $p['Stok']->Id, $g, '5', '192500.00', JenisMutasi::OpnameLebih)], JenisReferensiMutasi::StokOpname, 7);

        expect($lain->sudahAda)->toBeFalse()
            ->and(MutasiStok::query()->count())->toBe(3)
            ->and(BantuanBuku::AmbilSaldo($p['Stok']->Id, $g)?->JumlahTersedia)->toBe('15.0000');
    });
});
