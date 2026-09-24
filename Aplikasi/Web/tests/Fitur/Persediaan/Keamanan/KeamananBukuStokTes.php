<?php

declare(strict_types=1);

use App\Domain\Persediaan\Enum\JenisMutasi;
use App\Domain\Persediaan\Enum\JenisReferensiMutasi;
use App\Domain\Persediaan\Model\MutasiStok;
use Tests\Pendukung\Persediaan\BantuanBuku;
use Tests\Pendukung\Persediaan\BantuanPersediaan;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * F-05a QA keamanan buku stok (BR-05.1, CLAUDE.md #13): pemutaran ulang dokumen yang sudah tercatat (sinkron POS
 * F-07 yang terkirim ulang) tetap idempoten setelah periodenya dikunci; dokumen baru di periode terkunci tetap
 * ditolak `PeriodeTerkunci` (PRD v1.33: idempotensi diperiksa sebelum kunci periode).
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

describe('F-05a QA: idempotensi buku stok vs kunci periode', function (): void {
    it('dokumen yang sudah tercatat lalu dikirim ulang setelah periodenya dikunci = sudahAda, bukan PeriodeTerkunci', function (): void {
        $t = BantuanPersediaan::SiapkanTenant();
        $p = BantuanPersediaan::BuatProdukSemuaJenis($t['Pcs'], $t['Kg']);
        $idReferensi = BantuanBuku::AmbilIdReferensiBaru();
        $baris = [BantuanBuku::BuatBaris('M/1', $p['Stok']->Id, $t['Gudang']->Id, '24', '924000.00', JenisMutasi::PenyesuaianMasuk)];

        $pertama = BantuanBuku::Catat($baris, JenisReferensiMutasi::PenyesuaianStok, $idReferensi, '2026-08-14');
        BantuanPersediaan::KunciPeriode('2026-08', $t['Pemilik']->Id);
        $ulang = BantuanBuku::Catat($baris, JenisReferensiMutasi::PenyesuaianStok, $idReferensi, '2026-08-14');

        expect($ulang->sudahAda)->toBeTrue()
            ->and($ulang->baris['M/1']->idMutasiStok)->toBe($pertama->baris['M/1']->idMutasiStok)
            ->and(MutasiStok::query()->count())->toBe(1);

        $baru = BantuanBuku::TangkapPelanggaran(fn () => BantuanBuku::Catat($baris, JenisReferensiMutasi::PenyesuaianStok, null, '2026-08-20'));
        expect($baru->kode)->toBe('PeriodeTerkunci')
            ->and(MutasiStok::query()->count())->toBe(1);
    });
});
