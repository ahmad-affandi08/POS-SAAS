<?php

declare(strict_types=1);

use App\Domain\Katalog\Model\Produk;
use App\Domain\Persediaan\Data\DataBatchMasuk;
use App\Domain\Persediaan\Enum\JenisMutasi;
use App\Domain\Persediaan\Model\MutasiStok;
use App\Domain\Persediaan\Model\SaldoStok;
use App\Domain\Persediaan\Model\StokAwal;
use Carbon\CarbonImmutable;
use Tests\Pendukung\Persediaan\BantuanBuku;
use Tests\Pendukung\Persediaan\BantuanPersediaan;
use Tests\Pendukung\Persediaan\BantuanStokAwal;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * F-05a QA keamanan: batas kolom DECIMAL (CLAUDE.md #7, DesainF05a B). Nilai yang lolos validasi per baris tetapi
 * melampaui DECIMAL(18,2)/(18,4) setelah dibulatkan, dijumlah per dokumen, atau dijumlah ke saldo harus ditolak
 * sebagai pelanggaran aturan bisnis (422), bukan galat SQL "Out of range" (500) atau nilai terpotong.
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

/**
 * @return array{0: array<string, mixed>, 1: array<string, Produk>}
 */
function QaKSiapkanBatas(): array
{
    $t = BantuanPersediaan::SiapkanTenant();

    return [$t, BantuanPersediaan::BuatProdukSemuaJenis($t['Pcs'], $t['Kg'])];
}

describe('F-05a QA: batas DECIMAL stok awal', function (): void {
    it('Nilai baris yang lolos cek digit tetapi dibulatkan ke 17 digit ditolak 422, bukan 500', function (): void {
        [$t, $p] = QaKSiapkanBatas();
        BantuanPersediaan::MasukSebagai($this, $t['Tenant']->Id);

        // 1000 × 9.999.999.999.999,999995 = 9.999.999.999.999.999,995 (16 digit) → HalfUp 10^16 (17 digit).
        $this->post('/kelola/persediaan/stok-awal', BantuanStokAwal::IsiForm($t['Gudang'], [
            BantuanStokAwal::IsiBaris($p['Stok'], '1000', '9999999999999.999995'),
        ]))->assertStatus(302)->assertSessionHasErrors(['Baris.0.HppSatuan']);

        expect(StokAwal::query()->count())->toBe(0);
    });

    it('TotalNilai dokumen yang melampaui DECIMAL(18,2) ditolak 422 walau tiap baris dalam batas', function (): void {
        [$t, $p] = QaKSiapkanBatas();
        BantuanPersediaan::MasukSebagai($this, $t['Tenant']->Id);

        // Tiap baris 9.999.899.999.000.001 (16 digit); jumlah dua baris 17 digit.
        $this->post('/kelola/persediaan/stok-awal', BantuanStokAwal::IsiForm($t['Gudang'], [
            BantuanStokAwal::IsiBaris($p['Stok'], '99999999999', '99999'),
            BantuanStokAwal::IsiBaris($p['Produksi'], '99999999999', '99999'),
        ]))->assertStatus(302)->assertSessionHasErrors(['Baris']);

        expect(StokAwal::query()->count())->toBe(0);
    });
});

describe('F-05a QA: batas DECIMAL buku stok (BR-05.1)', function (): void {
    it('saldo nilai per pasangan yang melampaui DECIMAL(18,2) ditolak sebelum menulis apa pun', function (): void {
        [$t, $p] = QaKSiapkanBatas();
        $minyak = $p['Stok'];

        $galat = BantuanBuku::TangkapPelanggaran(fn () => BantuanBuku::Catat([
            BantuanBuku::BuatBaris('M/1', $minyak->Id, $t['Gudang']->Id, '1', '9000000000000000.00', JenisMutasi::PenyesuaianMasuk),
            BantuanBuku::BuatBaris('M/2', $minyak->Id, $t['Gudang']->Id, '1', '9000000000000000.00', JenisMutasi::PenyesuaianMasuk),
        ]));

        expect($galat->kode)->toBe('HppTidakValid')
            ->and(MutasiStok::query()->count())->toBe(0)
            ->and(SaldoStok::query()->where('IdProduk', $minyak->Id)->where('JumlahTersedia', '!=', 0)->count())->toBe(0);
    });

    it('saldo jumlah per pasangan yang melampaui DECIMAL(18,4) ditolak (dua batch di satu dokumen)', function (): void {
        [$t, $p] = QaKSiapkanBatas();
        $susu = $p['Batch'];
        $kedaluwarsa = CarbonImmutable::parse('2027-06-30');

        $galat = BantuanBuku::TangkapPelanggaran(fn () => BantuanBuku::Catat([
            BantuanBuku::BuatBaris('M/1', $susu->Id, $t['Gudang']->Id, '60000000000000', '0', JenisMutasi::PenyesuaianMasuk, batchMasuk: new DataBatchMasuk('UHT-A', $kedaluwarsa)),
            BantuanBuku::BuatBaris('M/2', $susu->Id, $t['Gudang']->Id, '60000000000000', '0', JenisMutasi::PenyesuaianMasuk, batchMasuk: new DataBatchMasuk('UHT-B', $kedaluwarsa)),
        ]));

        expect($galat->kode)->toBe('JumlahTidakValid')
            ->and(MutasiStok::query()->count())->toBe(0);
    });
});
