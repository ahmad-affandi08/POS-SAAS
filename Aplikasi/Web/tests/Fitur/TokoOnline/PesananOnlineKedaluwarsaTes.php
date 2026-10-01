<?php

declare(strict_types=1);

use App\Domain\Bersama\Dokumen\Model\RiwayatStatusDokumen;
use App\Domain\Penjualan\Aksi\BuatPesananOnline;
use App\Domain\Penjualan\Enum\StatusPesananOnline;
use App\Domain\Penjualan\Model\PengaturanTokoOnline;
use App\Domain\Penjualan\Model\PesananOnline;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Penjualan\BantuanTokoOnline;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * F-17 toko online: pesanan yang tidak pernah dikonfirmasi staf dihanguskan jadwal `pesanan-online:kedaluwarsa`
 * sesuai `PengaturanTokoOnline.MenitKedaluwarsa`, `Kedaluwarsa` tidak bisa dipasang staf dari back-office, dan
 * pesanan yang masih menunggu konfirmasi muncul di Kotak Tindakan.
 */

beforeEach(fn () => BantuanPendaftaran::SiapkanPrasyarat());

/**
 * @param  array<string, mixed>  $k
 */
function BuatPesananMenunggu(array $k, string $pemenuhan = 'AmbilSendiri'): PesananOnline
{
    $kiriman = BantuanTokoOnline::Kiriman($k, $pemenuhan);
    test()->postJson('/'.$k['Slug'].'/pesan', $kiriman)->assertCreated();
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);

    return PesananOnline::query()->where('Uuid', $kiriman['Uuid'])->sole();
}

it('menghanguskan pesanan yang lewat batas waktu toko, tetapi tidak menyentuh yang masih segar atau sudah dikonfirmasi', function (): void {
    $k = BantuanTokoOnline::Siapkan($this);
    PengaturanTokoOnline::query()->sole()->forceFill(['MenitKedaluwarsa' => 30])->save();

    $lewat = BuatPesananMenunggu($k);
    $segar = BuatPesananMenunggu($k);
    $dikonfirmasi = BuatPesananMenunggu($k);
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    PesananOnline::query()->whereKey([$lewat->Id, $dikonfirmasi->Id])->update(['DibuatPada' => now()->subMinutes(45)]);
    $dikonfirmasi->forceFill(['Status' => StatusPesananOnline::Dikonfirmasi])->save();

    $this->artisan('pesanan-online:kedaluwarsa')->assertSuccessful();
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);

    expect(PesananOnline::query()->whereKey($lewat->Id)->value('Status'))->toBe(StatusPesananOnline::Kedaluwarsa)
        ->and(PesananOnline::query()->whereKey($segar->Id)->value('Status'))->toBe(StatusPesananOnline::MenungguKonfirmasi)
        ->and(PesananOnline::query()->whereKey($dikonfirmasi->Id)->value('Status'))->toBe(StatusPesananOnline::Dikonfirmasi)
        ->and(RiwayatStatusDokumen::query()->where('JenisDokumen', PesananOnline::JENIS_DOKUMEN)->where('IdDokumen', $lewat->Id)
            ->where('StatusKe', StatusPesananOnline::Kedaluwarsa->value)->count())->toBe(1)
        ->and(DB::table('LogAudit')->where('IdTenant', $k['Tenant']->Id)->where('Peristiwa', 'pesanan-online.kedaluwarsa')->count())->toBe(1);

    // Jalan kedua tidak menghanguskan ulang: pesanan hangus sudah berstatus akhir.
    $this->artisan('pesanan-online:kedaluwarsa')->assertSuccessful();
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    expect(RiwayatStatusDokumen::query()->where('IdDokumen', $lewat->Id)->where('StatusKe', StatusPesananOnline::Kedaluwarsa->value)->count())->toBe(1);
});

it('pesanan hangus melepas jatah pesanan aktif nomor pelanggan', function (): void {
    $k = BantuanTokoOnline::Siapkan($this);
    PengaturanTokoOnline::query()->sole()->forceFill(['MenitKedaluwarsa' => 15])->save();

    for ($i = 0; $i < BuatPesananOnline::BATAS_AKTIF_PER_IP; $i++) {
        BuatPesananMenunggu($k);
    }
    $this->postJson('/'.$k['Slug'].'/pesan', BantuanTokoOnline::Kiriman($k))
        ->assertStatus(429)->assertJsonPath('Galat.Kode', 'TerlaluBanyakPesanan');

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    PesananOnline::query()->update(['DibuatPada' => now()->subMinutes(20)]);
    $this->artisan('pesanan-online:kedaluwarsa')->assertSuccessful();

    $this->postJson('/'.$k['Slug'].'/pesan', BantuanTokoOnline::Kiriman($k))->assertCreated();
});

it('staf tidak bisa memasang status Kedaluwarsa sendiri dari back-office', function (): void {
    $k = BantuanTokoOnline::Siapkan($this);
    $pesanan = BuatPesananMenunggu($k);
    BantuanOrganisasi::Masuk($this, $k['Pemilik'], $k['Tenant']->Id);

    $this->post('/kelola/toko-online/pesanan/'.$pesanan->Uuid.'/status', ['Status' => 'Kedaluwarsa'])
        ->assertSessionHasErrors('Status');
    $this->get('/kelola/toko-online')->assertOk()->assertInertia(fn (AssertableInertia $h) => $h
        ->where('OpsiStatusPesanan', fn ($opsi): bool => ! collect($opsi)->pluck('Nilai')->contains('Kedaluwarsa')));
});

it('pesanan menunggu konfirmasi muncul di Kotak Tindakan sebagai pengingat', function (): void {
    $k = BantuanTokoOnline::Siapkan($this);
    $pesanan = BuatPesananMenunggu($k);
    BantuanOrganisasi::Masuk($this, $k['Pemilik'], $k['Tenant']->Id);

    $butir = [];
    $this->get('/kelola/tindakan')->assertOk()->assertInertia(function (AssertableInertia $h) use (&$butir) {
        $butir = $h->toArray()['props']['Butir'];

        return $h;
    });
    $online = null;
    foreach ($butir as $b) {
        if ($b['Kunci'] === 'pesanan-online.menunggu-konfirmasi') {
            $online = $b;
        }
    }

    expect($online)->not->toBeNull()
        ->and($online['Jumlah'])->toBe(1)
        ->and($online['JenisDokumen'])->toBeNull()
        ->and($online['Rincian'][0]['Judul'])->toBe($pesanan->Nomor);
});
