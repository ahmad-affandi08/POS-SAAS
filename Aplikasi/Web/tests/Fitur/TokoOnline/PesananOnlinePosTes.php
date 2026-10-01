<?php

declare(strict_types=1);

use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Penjualan\Enum\StatusPesananOnline;
use App\Domain\Penjualan\Model\PesananOnline;
use Tests\Pendukung\Katalog\BantuanHarga;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Penjualan\BantuanTokoOnline;
use Tests\Pendukung\Tenant\BantuanPendaftaran;

/*
 * F-17 BR-17.3 (v3.33): pesanan online masuk ke aplikasi kasir tanpa membuka back-office — ringkasan murah untuk
 * polling 10 detik, daftar memuat pesanan yang menunggu konfirmasi, dan staf kasir menerima/menolak/memproses/
 * menandai siap pesanan outletnya.
 */

beforeEach(fn () => BantuanPendaftaran::SiapkanPrasyarat());

/**
 * @param  array<string, mixed>  $k
 */
function PesanOnlineUntukPos(array $k, string $pemenuhan = 'AmbilSendiri'): PesananOnline
{
    $kiriman = BantuanTokoOnline::Kiriman($k, $pemenuhan);
    test()->postJson('/'.$k['Slug'].'/pesan', $kiriman)->assertCreated();
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);

    return PesananOnline::query()->where('Uuid', $kiriman['Uuid'])->sole();
}

it('ringkas: menunggu, perlu ditagih, dan pesanan baru sejak polling terakhir', function (): void {
    $k = BantuanTokoOnline::Siapkan($this);
    $awal = $this->withToken($k['Token'])->getJson('/api/pos/v1/pesanan-online/ringkas')->assertOk()
        ->assertJsonPath('Menunggu', 0)->assertJsonPath('Baru', 0)->json('WaktuServer');

    $this->travel(2)->seconds();
    PesanOnlineUntukPos($k);
    $siap = PesanOnlineUntukPos($k);
    PesananOnline::query()->whereKey($siap->Id)->update(['Status' => StatusPesananOnline::Siap->value]);

    $this->withToken($k['Token'])->getJson('/api/pos/v1/pesanan-online/ringkas?sejak='.urlencode((string) $awal))->assertOk()
        ->assertJsonPath('Menunggu', 1)->assertJsonPath('PerluDitagih', 1)->assertJsonPath('Baru', 1);

    // Daftar untuk kasir kini juga memuat yang menunggu konfirmasi.
    $daftar = $this->withToken($k['Token'])->getJson('/api/pos/v1/pesanan-online')->assertOk()->json('Pesanan');
    expect(collect($daftar)->pluck('Status')->all())->toEqualCanonicalizing(['MenungguKonfirmasi', 'Siap']);
});

it('kasir menerima, memproses, dan menandai siap; kiriman ulang idempoten; status mundur ditolak', function (): void {
    $k = BantuanTokoOnline::Siapkan($this);
    $p = PesanOnlineUntukPos($k);
    $ubah = fn (string $status, ?string $alasan = null) => $this->withToken($k['Token'])
        ->postJson("/api/pos/v1/pesanan-online/{$p->Uuid}/status", ['UuidPengguna' => $k['Kasir']->Uuid, 'Status' => $status, 'Alasan' => $alasan]);

    $ubah('Dikonfirmasi')->assertOk()->assertJsonPath('Status', 'Dikonfirmasi');
    $ubah('Dikonfirmasi')->assertOk()->assertJsonPath('Status', 'Dikonfirmasi');
    $ubah('Diproses')->assertOk();
    $ubah('Siap')->assertOk()->assertJsonPath('Status', 'Siap');
    $ubah('Dikonfirmasi')->assertStatus(409)->assertJsonPath('Galat.Kode', 'StatusTidakValid');

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    expect($p->refresh()->DikonfirmasiOleh)->toBe($k['Kasir']->Id);
});

it('tolak wajib alasan; tanpa izin ditolak; pesanan outlet lain tidak ditemukan', function (): void {
    $k = BantuanTokoOnline::Siapkan($this);
    $p = PesanOnlineUntukPos($k);

    $this->withToken($k['Token'])->postJson("/api/pos/v1/pesanan-online/{$p->Uuid}/status", ['UuidPengguna' => $k['Kasir']->Uuid, 'Status' => 'Ditolak'])
        ->assertUnprocessable()->assertJsonPath('Galat.Detail.Alasan.0', fn (string $p): bool => $p !== '');

    $akuntan = BantuanHarga::TambahAnggotaOutlet($k['Tenant']->Id, PeranTenantBawaan::Akuntan, $k['Outlet']);
    $this->withToken($k['Token'])->postJson("/api/pos/v1/pesanan-online/{$p->Uuid}/status", ['UuidPengguna' => $akuntan->Uuid, 'Status' => 'Dikonfirmasi'])
        ->assertForbidden()->assertJsonPath('Galat.Kode', 'TanpaIzin');

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    PesananOnline::query()->whereKey($p->Id)->update(['IdOutlet' => BantuanHarga::BuatOutlet('LAIN', 'Outlet Lain')->Id]);
    $this->withToken($k['Token'])->postJson("/api/pos/v1/pesanan-online/{$p->Uuid}/status", ['UuidPengguna' => $k['Kasir']->Uuid, 'Status' => 'Dikonfirmasi'])
        ->assertNotFound()->assertJsonPath('Galat.Kode', 'PesananTidakDitemukan');

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    PesananOnline::query()->whereKey($p->Id)->update(['IdOutlet' => $k['Outlet']->Id]);
    $this->withToken($k['Token'])->postJson("/api/pos/v1/pesanan-online/{$p->Uuid}/status", ['UuidPengguna' => $k['Kasir']->Uuid, 'Status' => 'Ditolak', 'Alasan' => 'Bahan habis hari ini'])
        ->assertOk()->assertJsonPath('Status', 'Ditolak');
});
