<?php

declare(strict_types=1);

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Aksi\BuatNotifikasiPengguna;
use App\Domain\Organisasi\Enum\JenisNotifikasiPengguna;
use App\Domain\Organisasi\Model\NotifikasiPengguna;
use App\Domain\Organisasi\Model\PerangkatPengguna;
use Illuminate\Support\Facades\Queue;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Tenant\BantuanAutentikasi;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
});

it('mendaftarkan token, membaca dan menandai notifikasi, lalu menonaktifkan token saat keluar', function (): void {
    Queue::fake();
    $data = BantuanOrganisasi::BuatTenant('Toko Notifikasi');
    $data['Pemilik']->forceFill(['EmailDiverifikasiPada' => now()])->save();
    $masuk = $this->withHeader('X-Versi-Aplikasi', '1.0.0')->postJson('/api/pemilik/v1/masuk', [
        'Email' => $data['Pemilik']->Email,
        'KataSandi' => BantuanAutentikasi::KATA_SANDI,
        'NamaPerangkat' => 'Pixel Owner',
    ])->assertOk();
    $tokenAkses = (string) $masuk->json('Token');
    $header = ['X-Tenant' => $data['Tenant']->Uuid];
    $tokenFcm = str_repeat('token-fcm-uji-', 4);

    $this->withToken($tokenAkses)->withHeaders($header)->postJson('/api/pemilik/v1/token-notifikasi', [
        'Token' => $tokenFcm,
        'Platform' => 'Android',
        'NamaPerangkat' => 'Pixel 10',
    ])->assertCreated()->assertJsonPath('Perangkat.Platform', 'Android');

    $perangkat = PerangkatPengguna::query()->sole();
    expect($perangkat->Token)->toBe($tokenFcm)
        ->and($perangkat->HashToken)->toBe(hash('sha256', $tokenFcm))
        ->and($perangkat->Aktif)->toBeTrue();

    app(KonteksTenant::class)->Atur($data['Tenant']->Id);
    $notifikasi = app(BuatNotifikasiPengguna::class)->Jalankan(
        $data['Pemilik']->Id,
        JenisNotifikasiPengguna::StokKritis,
        'uji:stok:2026-09-29',
        'Stok menipis',
        '3 produk di bawah minimum.',
        ['Tautan' => 'notifikasi'],
    );

    $this->withToken($tokenAkses)->withHeaders($header)->getJson('/api/pemilik/v1/notifikasi')
        ->assertOk()
        ->assertJsonPath('BelumDibaca', 1)
        ->assertJsonPath('Notifikasi.0.Uuid', $notifikasi->Uuid)
        ->assertJsonPath('Notifikasi.0.Data.Tautan', 'notifikasi');

    $this->withToken($tokenAkses)->withHeaders($header)->patchJson('/api/pemilik/v1/notifikasi', [
        'Uuid' => [$notifikasi->Uuid],
    ])->assertOk()->assertJsonPath('Jumlah', 1);
    expect(NotifikasiPengguna::query()->whereKey($notifikasi->Id)->firstOrFail()->DibacaPada)->not->toBeNull();

    $this->withToken($tokenAkses)->postJson('/api/pemilik/v1/keluar')->assertNoContent();
    expect($perangkat->fresh()?->Aktif)->toBeFalse();
});
