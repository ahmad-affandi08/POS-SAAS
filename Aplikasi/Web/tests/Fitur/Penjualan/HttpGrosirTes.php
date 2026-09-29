<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\Piutang;
use App\Domain\Penjualan\Enum\StatusDokumenGrosir;
use App\Domain\Penjualan\Enum\StatusPesananGrosir;
use App\Domain\Penjualan\Model\FakturPenjualan;
use App\Domain\Penjualan\Model\PesananGrosir;
use App\Domain\Penjualan\Model\ReturGrosir;
use App\Domain\Penjualan\Model\SuratJalan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Katalog\BantuanHarga;
use Tests\Pendukung\Katalog\BantuanKatalog;
use Tests\Pendukung\Persediaan\BantuanPersediaan;
use Tests\Pendukung\Persediaan\BantuanStokAwal;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * Back-office grosir lewat rute (routes/Grosir.php, F-12 §9.7): alur lengkap SO → surat jalan → faktur → retur, setiap halaman
 * Inertia ada di disk, TabelData JSON, izin `grosir.kelola`, dan isolasi tenant.
 *
 * Yang paling penting dijaga di sini adalah **harga tidak datang dari klien**: formulirnya memang tidak punya bidang
 * harga, dan test membuktikan harga tersimpan = harga katalog walau permintaannya tidak menyebut harga sama sekali.
 */

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-27 03:00:00');
    BantuanPendaftaran::SiapkanPrasyarat();
    $this->k = BantuanPersediaan::SiapkanTenant('Grosir Sumber Pangan');
    $this->gula = BantuanKatalog::BuatProduk(['Nama' => 'Gula Pasir Kemasan 1 kg'], '15000.00', $this->k['Pcs']);
    $this->satuan = BantuanHarga::SatuanDasar($this->gula);
    BantuanStokAwal::BuatDanPosting(
        $this->k['Gudang'],
        [BantuanStokAwal::Baris($this->gula, '1000', '11000')],
        $this->k['Pemilik']->Id,
        '2026-09-01',
    );
    $this->toko = Pelanggan::query()->create([
        'Nama' => 'Toko Makmur Jaya',
        'NoHp' => '6281355550005',
        'LimitKredit' => '50000000',
        'TerminHari' => 30,
    ]);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

describe('HTTP back-office grosir', function (): void {
    it('alur lengkap lewat rute: draf SO, konfirmasi, kirim, faktur, nomor Faktur Pajak, retur', function (): void {
        BantuanPersediaan::MasukSebagai($this, $this->k['Tenant']->Id);

        $this->get('/kelola/grosir/pesanan')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Grosir/Pesanan/Daftar')->has('OpsiStatus', 5));
        $this->get('/kelola/grosir/pesanan/buat')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Grosir/Pesanan/Form')->where('Isian', null)->has('OpsiOutlet', 1));

        // Perhatikan: tidak ada 'Harga' di permintaan ini sama sekali.
        $this->post('/kelola/grosir/pesanan', [
            'UuidPelanggan' => $this->toko->Uuid,
            'UuidOutlet' => $this->k['Outlet']->Uuid,
            'Tanggal' => '2026-09-27',
            'Baris' => [['UuidProduk' => $this->gula->Uuid, 'UuidProdukSatuan' => $this->satuan->Uuid, 'Jumlah' => '200']],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $pesanan = PesananGrosir::query()->sole();
        expect($pesanan->Status)->toBe(StatusPesananGrosir::Draf)
            // Harga datang dari katalog, bukan dari peramban.
            ->and($pesanan->Detail()->value('Harga'))->toBe('15000.00')
            ->and($pesanan->Total)->toBe('3000000.00');

        $this->get("/kelola/grosir/pesanan/{$pesanan->Uuid}")->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Grosir/Pesanan/Detail')
            ->where('Tindakan.Konfirmasi', true)
            ->where('Tindakan.Kirim', false)
            ->where('Pesanan.Total', '3000000.00')
            ->has('Baris', 1));
        $this->get("/kelola/grosir/pesanan/{$pesanan->Uuid}/ubah")->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Grosir/Pesanan/Form')->has('Isian.Baris', 1));

        $this->post("/kelola/grosir/pesanan/{$pesanan->Uuid}/konfirmasi")->assertSessionHasNoErrors()->assertRedirect();
        expect($pesanan->refresh()->Status)->toBe(StatusPesananGrosir::Dikonfirmasi);

        $this->post("/kelola/grosir/pesanan/{$pesanan->Uuid}/kirim", [
            'UuidGudang' => $this->k['Gudang']->Uuid,
            'Tanggal' => '2026-09-27',
            'NamaPengirim' => 'Sopir Pak Slamet',
            'Baris' => [['Urutan' => 1, 'Jumlah' => '200']],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $suratJalan = SuratJalan::query()->sole();
        expect($suratJalan->Status)->toBe(StatusDokumenGrosir::Diposting)
            ->and($suratJalan->Total)->toBe('3000000.00')
            ->and($pesanan->refresh()->Status)->toBe(StatusPesananGrosir::Selesai);

        $this->get('/kelola/grosir/surat-jalan')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Grosir/SuratJalan/Daftar')->has('SuratJalan.Data', 1));
        $this->get("/kelola/grosir/surat-jalan/{$suratJalan->Uuid}")->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Grosir/SuratJalan/Detail')
            ->where('Tindakan.Batalkan', true)
            ->where('SuratJalan.TotalHpp', '2200000.00')
            ->has('Jurnal', 1));

        $this->get('/kelola/grosir/faktur/buat')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Grosir/Faktur/Buat')->has('SuratJalan.Data', 1));
        $this->post('/kelola/grosir/faktur', [
            'UuidSuratJalan' => [$suratJalan->Uuid],
            'Tanggal' => '2026-09-27',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $faktur = FakturPenjualan::query()->sole();
        expect($faktur->Status)->toBe(StatusDokumenGrosir::Diposting)
            ->and($faktur->Total)->toBe('3000000.00')
            ->and($suratJalan->refresh()->IdFakturPenjualan)->toBe($faktur->Id)
            // BR-12.5: piutangnya memakai tabel yang sama dengan penjualan tempo.
            ->and(Piutang::query()->where('IdFakturPenjualan', $faktur->Id)->exists())->toBeTrue();

        $this->get('/kelola/grosir/faktur')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Grosir/Faktur/Daftar')->where('Faktur.Data.0.Sisa', '3000000.00'));
        $this->get("/kelola/grosir/faktur/{$faktur->Uuid}")->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Grosir/Faktur/Detail')->has('SuratJalan', 1)->has('Jurnal', 1));

        $this->put("/kelola/grosir/faktur/{$faktur->Uuid}/nomor-pajak", ['NomorFakturPajak' => '0100002512345678'])
            ->assertSessionHasNoErrors()->assertRedirect();
        expect($faktur->refresh()->NomorFakturPajak)->toBe('0100002512345678');

        // Retur (BR-12.7): jumlah & kondisi saja yang dikirim; harga dan HPP-nya dari snapshot surat jalan.
        $this->get("/kelola/grosir/retur/buat/{$suratJalan->Uuid}")->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Grosir/Retur/Buat')
            ->where('Baris.0.SisaRetur', '200.0000')
            ->has('OpsiKondisi', 2));
        $this->post('/kelola/grosir/retur', [
            'UuidSuratJalan' => $suratJalan->Uuid,
            'Tanggal' => '2026-09-27',
            'Alasan' => 'Sepuluh sak basah kena hujan di gudang pembeli.',
            'Baris' => [['Urutan' => 1, 'Jumlah' => '10', 'Kondisi' => 'Rusak']],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $retur = ReturGrosir::query()->sole();
        expect($retur->Total)->toBe('150000.00')
            // Penyerahannya sudah difakturkan, jadi returnya menjadi nota kredit yang mengurangi piutang faktur itu.
            ->and($retur->MengurangiPiutang)->toBeTrue()
            ->and($retur->IdFakturPenjualan)->toBe($faktur->Id)
            ->and(Piutang::query()->where('IdFakturPenjualan', $faktur->Id)->sole()->AmbilSisa()->KeString())->toBe('2850000.00')
            ->and($suratJalan->refresh()->Detail()->value('JumlahDiretur'))->toBe('10.0000');

        $this->get('/kelola/grosir/retur')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Grosir/Retur/Daftar')->has('Retur.Data', 1)->where('Retur.Data.0.MengurangiPiutang', true));
        $this->get("/kelola/grosir/retur/{$retur->Uuid}")->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->component('Kelola/Grosir/Retur/Detail')
            ->where('Retur.Total', '150000.00')
            ->where('Baris.0.LabelKondisi', 'Rusak')
            ->has('Jurnal', 1));

        // Sisa yang bisa diretur berkurang, dan returnya tampil di halaman surat jalannya.
        $this->get("/kelola/grosir/surat-jalan/{$suratJalan->Uuid}")->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
            ->where('Baris.0.SisaRetur', '190.0000')->has('Retur', 1));

        $this->post("/kelola/grosir/retur/{$retur->Uuid}/batalkan", ['Alasan' => 'Salah input, yang basah cuma lima sak.'])
            ->assertSessionHasNoErrors()->assertRedirect();
        expect($retur->refresh()->Status)->toBe(StatusDokumenGrosir::Dibatalkan)
            ->and(Piutang::query()->where('IdFakturPenjualan', $faktur->Id)->sole()->AmbilSisa()->KeString())->toBe('3000000.00')
            ->and($suratJalan->refresh()->Detail()->value('JumlahDiretur'))->toBe('0.0000');
    });

    it('TabelData JSON & pencarian produk/pelanggan menjawab', function (): void {
        BantuanPersediaan::MasukSebagai($this, $this->k['Tenant']->Id);

        // `getJson` mengirim Accept: application/json tanpa X-Inertia, jadi yang dikembalikan data tabelnya.
        $this->getJson('/kelola/grosir/pesanan')->assertOk()->assertJsonStructure(['Data', 'Meta']);
        $this->getJson('/kelola/grosir/produk/cari?kata=Gula')->assertOk()
            ->assertJsonPath('Data.0.Nama', 'Gula Pasir Kemasan 1 kg')
            // Satuan jual ikut, dan tidak ada harga di hasil pencarian (harga milik server saat draf disimpan).
            ->assertJsonCount(1, 'Data.0.Satuan');
        $this->getJson('/kelola/grosir/pelanggan/cari?kata=Makmur')->assertOk();
    });

    it('tanpa izin grosir.kelola ditolak, dan dokumen tenant lain 404', function (): void {
        // Kasir tidak memegang grosir.kelola (izin itu milik Manajer Outlet & Supervisor).
        BantuanKatalog::MasukSebagai($this, $this->k['Tenant']->Id, PeranTenantBawaan::Kasir);
        $this->get('/kelola/grosir/pesanan')->assertForbidden();

        BantuanPersediaan::MasukSebagai($this, $this->k['Tenant']->Id);
        $this->get('/kelola/grosir/pesanan/'.strtoupper((string) Str::ulid()))->assertNotFound();
    });
});
