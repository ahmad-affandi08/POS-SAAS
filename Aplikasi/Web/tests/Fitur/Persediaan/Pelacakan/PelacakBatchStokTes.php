<?php

declare(strict_types=1);

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Kuantitas;
use App\Domain\Persediaan\Data\DataBatchMasuk;
use App\Domain\Persediaan\Layanan\PelacakBatchStok;
use App\Domain\Persediaan\Model\BatchStok;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Persediaan\BantuanPelacakan;
use Tests\Pendukung\Persediaan\PemeriksaInvarian;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

describe('F-05a PelacakBatchStok (DesainF05a C.4, F-05g)', function (): void {
    it('KunciMasuk membuat batch sekali per (produk, lokasi stok, nomor batch) lalu mengunci FOR UPDATE', function (): void {
        $t = BantuanPelacakan::SiapkanTenant();
        $susu = $t['Produk']['Batch'];
        $pelacak = app(PelacakBatchStok::class);
        $batch = new DataBatchMasuk('  UHT-2026-0917A ', CarbonImmutable::parse('2027-03-17'));
        $kueri = [];
        DB::listen(function (QueryExecuted $q) use (&$kueri): void {
            $kueri[] = $q->sql;
        });

        $pertama = DB::transaction(fn (): BatchStok => $pelacak->KunciMasuk($susu->Id, $t['Gudang']->Id, $batch, BigDecimal::of('17250.5')));
        $kedua = DB::transaction(fn (): BatchStok => $pelacak->KunciMasuk($susu->Id, $t['Gudang']->Id, $batch, BigDecimal::of('99999')));
        $lain = DB::transaction(fn (): BatchStok => $pelacak->KunciMasuk($susu->Id, $t['GudangBelakang']->Id, $batch));

        expect($kedua->Id)->toBe($pertama->Id)
            ->and($pertama->NomorBatch)->toBe('UHT-2026-0917A')
            ->and($pertama->Uuid)->toHaveLength(26)
            ->and($pertama->IdTenant)->toBe($t['Tenant']->Id)
            ->and($pertama->JumlahSisa)->toBe('0.0000')
            ->and($pertama->TanggalKedaluwarsa?->toDateString())->toBe('2027-03-17')
            ->and($kedua->HppSatuan)->toBe('17250.500000')
            ->and($lain->Id)->not->toBe($pertama->Id)
            ->and(BatchStok::query()->count())->toBe(2)
            ->and(collect($kueri)->contains(fn (string $sql): bool => str_contains($sql, 'from `BatchStok`') && str_contains($sql, 'for update')))->toBeTrue();
    });

    it('F-05g: nomor batch sama dengan kedaluwarsa berbeda ditolak BatchKedaluwarsaBerbeda', function (): void {
        $t = BantuanPelacakan::SiapkanTenant();
        $susu = $t['Produk']['Batch'];
        $pelacak = app(PelacakBatchStok::class);
        $pelacak->KunciMasuk($susu->Id, $t['Gudang']->Id, new DataBatchMasuk('UHT-2026-0917A', CarbonImmutable::parse('2027-03-17')));

        $galat = null;

        try {
            $pelacak->KunciMasuk($susu->Id, $t['Gudang']->Id, new DataBatchMasuk('UHT-2026-0917A', CarbonImmutable::parse('2027-04-01')));
        } catch (PelanggaranAturanBisnis $e) {
            $galat = $e;
        }

        expect($galat?->kode)->toBe('BatchKedaluwarsaBerbeda')
            ->and($galat?->bidang)->toBe('TanggalKedaluwarsa')
            ->and($galat?->getMessage())->toContain('2027-03-17')
            ->and($galat?->detail['TanggalDiminta'] ?? null)->toBe('2027-04-01');

        expect(fn () => $pelacak->KunciMasuk($susu->Id, $t['Gudang']->Id, new DataBatchMasuk('UHT-2026-0917A', null)))
            ->toThrow(PelanggaranAturanBisnis::class, 'kedaluwarsa 2027-03-17, bukan kosong');
    });

    it('BR-05.2: batch tidak pernah minus walau produk & tenant BolehMinus (StokBatchTidakCukup)', function (): void {
        $t = BantuanPelacakan::SiapkanTenant(stokBolehMinus: true);
        $susu = $t['Produk']['Batch'];
        $susu->forceFill(['BolehMinus' => true])->save();
        $pelacak = app(PelacakBatchStok::class);
        $batch = $pelacak->KunciMasuk($susu->Id, $t['Gudang']->Id, new DataBatchMasuk('UHT-2026-0917A', CarbonImmutable::parse('2027-03-17')));

        $pelacak->Terapkan($batch, Kuantitas::Dari('24'));
        $pelacak->Terapkan($batch, Kuantitas::Dari('-6.5'));
        expect($batch->refresh()->JumlahSisa)->toBe('17.5000');

        $galat = null;

        try {
            $pelacak->Terapkan($batch, Kuantitas::Dari('-18'));
        } catch (PelanggaranAturanBisnis $e) {
            $galat = $e;
        }

        expect($galat?->kode)->toBe('StokBatchTidakCukup')
            ->and($galat?->getMessage())->toBe('Stok batch UHT-2026-0917A tidak cukup: tersedia 17.5, dibutuhkan 18.')
            ->and($galat?->detail)->toMatchArray(['NomorBatch' => 'UHT-2026-0917A', 'Tersedia' => '17.5000', 'Diminta' => '18.0000'])
            ->and($batch->refresh()->JumlahSisa)->toBe('17.5000');

        $pelacak->Terapkan($batch, Kuantitas::Dari('-17.5'));
        expect($batch->refresh()->JumlahSisa)->toBe('0.0000');
    });

    it('KunciKeluar: batch produk/lokasi lain atau tenant lain ditolak BatchTidakDikenal (isolasi tenant)', function (): void {
        $a = BantuanPelacakan::SiapkanTenant('Apotek Sehat Sentosa');
        $pelacak = app(PelacakBatchStok::class);
        $batchA = $pelacak->KunciMasuk($a['Produk']['Batch']->Id, $a['Gudang']->Id, new DataBatchMasuk('AMX-500-2611', CarbonImmutable::parse('2027-11-30')));

        $kunci = $pelacak->KunciKeluar($batchA->Id, $a['Produk']['Batch']->Id, $a['Gudang']->Id);
        expect($kunci->Id)->toBe($batchA->Id);

        expect(fn () => $pelacak->KunciKeluar($batchA->Id, $a['Produk']['Stok']->Id, $a['Gudang']->Id))->toThrow(PelanggaranAturanBisnis::class, 'Batch tidak ditemukan')
            ->and(fn () => $pelacak->KunciKeluar($batchA->Id, $a['Produk']['Batch']->Id, $a['GudangBelakang']->Id))->toThrow(PelanggaranAturanBisnis::class, 'Batch tidak ditemukan')
            ->and(fn () => $pelacak->KunciKeluar(987654321, $a['Produk']['Batch']->Id, $a['Gudang']->Id))->toThrow(PelanggaranAturanBisnis::class, 'Batch tidak ditemukan');

        $b = BantuanPelacakan::SiapkanTenant('Apotek Lain Jaya');
        BantuanOrganisasi::AturKonteks($b['Tenant']->Id);

        try {
            $pelacak->KunciKeluar($batchA->Id, $a['Produk']['Batch']->Id, $a['Gudang']->Id);
            $kode = null;
        } catch (PelanggaranAturanBisnis $e) {
            $kode = $e->kode;
        }

        expect($kode)->toBe('BatchTidakDikenal')
            ->and(BatchStok::query()->count())->toBe(0);
    });

    it('invarian: BatchStok.JumlahSisa = Σ MutasiStok batch setelah masuk & keluar', function (): void {
        $t = BantuanPelacakan::SiapkanTenant();
        $susu = $t['Produk']['Batch'];
        $pelacak = app(PelacakBatchStok::class);
        $batch = $pelacak->KunciMasuk($susu->Id, $t['Gudang']->Id, new DataBatchMasuk('UHT-2026-0917A', CarbonImmutable::parse('2027-03-17')));

        $pelacak->Terapkan($batch, Kuantitas::Dari('48'));
        BantuanPelacakan::BuatMutasiMentah($susu, $t['Gudang'], '48.0000', ['IdBatchStok' => $batch->Id]);
        $keluar = $pelacak->KunciKeluar($batch->Id, $susu->Id, $t['Gudang']->Id);
        $pelacak->Terapkan($keluar, Kuantitas::Dari('-12'));
        BantuanPelacakan::BuatMutasiMentah($susu, $t['Gudang'], '-12.0000', ['IdBatchStok' => $batch->Id, 'SaldoSetelah' => '36.0000']);

        expect(PemeriksaInvarian::PeriksaBatch($t['Tenant']->Id))->toBe([]);
    });
});
