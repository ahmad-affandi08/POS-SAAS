<?php

declare(strict_types=1);

use App\Domain\Kasir\Enum\JenisKategoriKas;
use App\Domain\Organisasi\Aksi\AturPinSendiri;
use App\Domain\Organisasi\Aksi\CabutPerangkat;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Organisasi\Layanan\VerifierPinOffline;
use App\Domain\Organisasi\Model\OutletPengguna;
use App\Domain\Organisasi\Model\Perangkat;
use Tests\Pendukung\Akuntansi\BantuanJurnal;
use Tests\Pendukung\Kasir\BantuanKasir;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Organisasi\BantuanPerangkat;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

/** Membuka verifier terbungkus dengan kunci perangkat (sama dengan yang dilakukan aplikasi kasir). */
function BukaVerifierPinUji(array $pin, string $kunciBase64): string
{
    $sandi = (string) base64_decode($pin['Sandi'], true);
    $hasil = openssl_decrypt(substr($sandi, 0, -16), 'aes-256-gcm', (string) base64_decode($kunciBase64, true), OPENSSL_RAW_DATA, (string) base64_decode($pin['Nonce'], true), substr($sandi, -16));

    return is_string($hasil) ? $hasil : '';
}

describe('F-06 PIN kasir offline & data awal (GET /api/pos/v1/data-awal)', function (): void {
    it('vektor uji bersama Spesifikasi/VektorUjiPin: Argon2id & AES-256-GCM PHP sama dengan nilai harapan', function (): void {
        $vektor = json_decode((string) file_get_contents(base_path('../../Spesifikasi/VektorUjiPin/VerifierPin.json')), true, flags: JSON_THROW_ON_ERROR);

        expect($vektor['Parameter'])->toBe(VerifierPinOffline::AmbilParameter());

        foreach ($vektor['Kasus'] as $kasus) {
            $hash = VerifierPinOffline::HitungHash($kasus['Pin'], (string) base64_decode($kasus['Garam'], true));
            expect(base64_encode($hash))->toBe($kasus['Hash'])
                ->and(BukaVerifierPinUji($kasus, $vektor['KunciPerangkat']))->toBe($hash);
        }
    });

    it('§25.2 no. 3: aktivasi mengirim kunci PIN perangkat; data awal membungkus verifier yang cocok dengan PIN, tanpa hash PIN mentah', function (): void {
        $k = BantuanKasir::Siapkan($this);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        ['Kode' => $kode] = BantuanPerangkat::BuatPerangkat($k['Tenant']->Id, $k['Outlet'], nama: 'Kasir Teras');
        $aktivasi = $this->postJson('/api/pos/v1/perangkat/aktivasi', ['Kode' => $kode, 'Platform' => 'Android', 'VersiAplikasi' => '1.0.0'])->assertCreated();
        $kunci = (string) $aktivasi->json('KunciPinOffline');
        app(AturPinSendiri::class)->Jalankan($k['Tenant']->Id, $k['Kasir']->Id, '739415');

        $respons = $this->withToken((string) $aktivasi->json('TokenPerangkat'))->getJson('/api/pos/v1/data-awal')->assertOk();
        $staf = collect($respons->json('Staf'))->keyBy('Uuid');
        $kasir = $staf->get($k['Kasir']->Uuid);

        expect(strlen((string) base64_decode($kunci, true)))->toBe(32)
            ->and($respons->json('PinOffline.Tersedia'))->toBeTrue()
            ->and($respons->json('PinOffline.Parameter'))->toBe(VerifierPinOffline::AmbilParameter())
            ->and($kasir['PinDiatur'])->toBeTrue()
            ->and($kasir['Izin'])->toContain('penjualan.buat')
            ->and(BukaVerifierPinUji($kasir['Pin'], $kunci))->toBe(VerifierPinOffline::HitungHash('739415', (string) base64_decode($kasir['Pin']['Garam'], true)))
            ->and(BukaVerifierPinUji($kasir['Pin'], $kunci))->not->toBe(VerifierPinOffline::HitungHash('739416', (string) base64_decode($kasir['Pin']['Garam'], true)))
            ->and($staf->get($k['Supervisor']->Uuid)['Pin'])->toBeNull()
            ->and($staf->get($k['Supervisor']->Uuid)['PinDiatur'])->toBeFalse()
            ->and($respons->getContent())->not->toContain('$2y$')
            ->and($respons->getContent())->not->toContain('HashPin');
    });

    it('staf hanya anggota aktif dengan akses outlet perangkat; kategori kas hanya yang aktif; pengaturan kasir ikut', function (): void {
        $k = BantuanKasir::Siapkan($this);
        BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
        $cabang = BantuanJurnal::BuatOutlet();
        $kasirCabang = BantuanOrganisasi::TambahAnggota($k['Tenant']->Id, PeranTenantBawaan::Kasir, semuaOutlet: false);
        OutletPengguna::query()->create(['IdOutlet' => $cabang->Id, 'IdPengguna' => $kasirCabang->Id, 'IdPeran' => BantuanOrganisasi::Peran($k['Tenant']->Id, PeranTenantBawaan::Kasir)->Id]);
        BantuanKasir::BuatKategori('Parkir lama', JenisKategoriKas::Keluar, '6-9000', aktif: false);

        $respons = $this->withToken($k['Token'])->getJson('/api/pos/v1/data-awal')->assertOk();
        $uuidStaf = array_column($respons->json('Staf'), 'Uuid');

        expect($uuidStaf)->toContain($k['Kasir']->Uuid, $k['Supervisor']->Uuid, $k['Pemilik']->Uuid)
            ->and($uuidStaf)->not->toContain($kasirCabang->Uuid)
            ->and(array_column($respons->json('KategoriKas'), 'Nama'))->toBe(['Beli es batu & galon', 'Tambahan uang receh'])
            ->and($respons->json('Pengaturan'))->toBe([
                'BatasKasKeluar' => '200000.00',
                'ShiftBersama' => false,
                // F-07b: batas diskon (BR-07.3) & pembulatan tunai (BR-08.6).
                'BatasDiskonManual' => '10.00',
                'BatasDiskonPenyetuju' => '30.00',
                'PembulatanTunai' => null,
                // F-11: tutup shift buta & toleransi selisih kas (§19.2).
                'TutupShiftButa' => true,
                'ToleransiSelisihKas' => '10000.00',
                // F-09: batas hari retur sejak tanggal bisnis penjualan.
                'BatasHariRetur' => 7,
                // F-12: tempo butuh penyetuju bila ada piutang lewat jatuh tempo > N hari (bawaan 0).
                'BatasHariLewatJatuhTempo' => 0,
            ]);
    });

    it('isolasi & pencabutan: staf tenant lain tidak ikut; perangkat dicabut 403 dan kunci PIN-nya dikosongkan', function (): void {
        $a = BantuanKasir::Siapkan($this, 'Kopi Senja Solo');
        $b = BantuanKasir::Siapkan($this, 'Warung Bakso Pak Kumis');

        $staf = array_column($this->withToken($b['Token'])->getJson('/api/pos/v1/data-awal')->assertOk()->json('Staf'), 'Uuid');
        expect($staf)->not->toContain($a['Kasir']->Uuid);

        BantuanOrganisasi::AturKonteks($a['Tenant']->Id);
        app(CabutPerangkat::class)->Jalankan($a['Perangkat']);
        $this->withToken($a['Token'])->getJson('/api/pos/v1/data-awal')->assertForbidden();

        BantuanOrganisasi::AturKonteks($a['Tenant']->Id);
        expect(Perangkat::query()->findOrFail($a['Perangkat']->Id)->KunciPinOffline)->toBeNull();
    });
});
