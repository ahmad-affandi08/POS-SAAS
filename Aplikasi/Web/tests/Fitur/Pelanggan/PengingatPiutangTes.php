<?php

declare(strict_types=1);

use App\Domain\Bersama\Audit\Model\LogAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Pelanggan\Enum\JenisPengingatPiutang;
use App\Domain\Pelanggan\Enum\KanalPengingatPiutang;
use App\Domain\Pelanggan\Enum\StatusPengingatPiutang;
use App\Domain\Pelanggan\Enum\StatusPiutang;
use App\Domain\Pelanggan\Layanan\PengirimPengingatPiutang;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\PengingatPiutang;
use App\Domain\Pelanggan\Model\Piutang;
use App\Domain\Pelanggan\Surel\PengingatPiutangPelanggan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Kasir\BantuanKasir;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Penjualan\BantuanPenjualan;
use Tests\Pendukung\Tenant\BantuanPendaftaran;
use Tests\TestCase;

/*
 * D-23 D bagian 4b: pengingat piutang ke pelanggan lewat WhatsApp/email. Kirim manual dari daftar piutang (jeda 12 jam
 * per nota), otomatis tiap pagi bila diaktifkan (sekali "akan jatuh tempo" H-n, sekali "sudah lewat"), piutang lunas
 * saat akan dikirim = dibatalkan, tujuan terenkripsi, izin `pelanggan.kelola`, isolasi tenant.
 */

beforeEach(function (): void {
    BantuanPendaftaran::SiapkanPrasyarat();
    Mail::fake();
});

/**
 * Dua penjualan tempo Rp 77.000 ke Toko Makmur Jaya (termin 30 hari, punya email), pemilik masuk.
 *
 * @return array<string, mixed>
 */
function SiapkanPiutangPengingat(TestCase $tes, string $nama = 'Grosir Sembako Pengingat'): array
{
    $k = BantuanPenjualan::Siapkan($tes, $nama);
    $produk = BantuanPenjualan::BuatProdukBerstok($k['Gudang'], $k['Pemilik']->Id);
    $toko = Pelanggan::query()->create([
        'Nama' => 'Toko Makmur Jaya', 'NoHp' => '6281355550001', 'Email' => 'Tagihan@MakmurJaya.co.id',
        'LimitKredit' => '5000000', 'TerminHari' => 30,
    ]);
    $item = fn (): array => BantuanPenjualan::Item(
        $k,
        ['Baris' => [['Produk' => $produk, 'Jumlah' => '2', 'Harga' => '38500.00']], 'Pembayaran' => [['Metode' => $k['Tempo'], 'Jumlah' => '77000.00']]],
        ['UuidPelanggan' => $toko->Uuid],
    );

    expect(BantuanKasir::KirimRingkas($tes, $k['Token'], [$item(), $item()]))->toBe([['Diterima', null], ['Diterima', null]]);
    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    BantuanOrganisasi::Masuk($tes, $k['Pemilik'], $k['Tenant']->Id);
    $piutang = Piutang::query()->orderBy('Id')->get();

    return $k + ['Toko' => $toko, 'P1' => $piutang[0], 'P2' => $piutang[1]];
}

it('kirim manual: email ke pelanggan (WhatsApp belum aktif), tujuan terenkripsi, jeda per nota, tampil di daftar; Kasir ditolak', function (): void {
    $k = SiapkanPiutangPengingat($this);

    $this->post("/kelola/piutang/{$k['P1']->Uuid}/pengingat")
        ->assertSessionHasNoErrors()
        ->assertSessionHas('Kilat', "Pengingat {$k['P1']->Nomor} sedang dikirim lewat email.");

    Mail::assertSent(PengingatPiutangPelanggan::class, fn (PengingatPiutangPelanggan $s): bool => $s->hasTo('tagihan@makmurjaya.co.id')
        && $s->nomor === $k['P1']->Nomor
        && str_contains($s->isi, 'Halo Toko Makmur Jaya')
        && str_contains($s->isi, 'Rp 77.000')
        && str_contains($s->isi, 'Abaikan pesan ini bila sudah dibayar'));

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    $pengingat = PengingatPiutang::query()->sole();
    expect($pengingat->Status)->toBe(StatusPengingatPiutang::Terkirim)
        ->and($pengingat->Kanal)->toBe(KanalPengingatPiutang::Email)
        ->and($pengingat->Jenis)->toBe(JenisPengingatPiutang::Manual)
        ->and($pengingat->DikirimOleh)->toBe($k['Pemilik']->Id)
        ->and((string) DB::table('PengingatPiutang')->value('Tujuan'))->not->toContain('makmurjaya')
        ->and($pengingat->toArray())->not->toHaveKey('Tujuan');

    // Jeda: nota yang sama tidak bisa diingatkan lagi dalam 12 jam; nota lain boleh.
    $this->post("/kelola/piutang/{$k['P1']->Uuid}/pengingat")->assertSessionHasErrors(['Umum' => 'Pelanggan ini baru saja diingatkan untuk nota yang sama. Coba lagi besok.']);
    $this->post("/kelola/piutang/{$k['P2']->Uuid}/pengingat")->assertSessionHasNoErrors();
    Mail::assertSent(PengingatPiutangPelanggan::class, 2);

    $this->get('/kelola/piutang')->assertInertia(fn (AssertableInertia $h) => $h
        ->where('Izin.Ingatkan', true)
        ->where('Pengingat', ['Aktif' => false, 'HariSebelum' => 3, 'IngatkanSaatLewat' => true])
        ->where('Piutang.Data.0.PengingatTerakhir.Status', 'Terkirim')
        ->where('Piutang.Data.0.PengingatTerakhir.Kanal', 'Email'));

    $kasir = BantuanOrganisasi::TambahAnggota($k['Tenant']->Id, PeranTenantBawaan::Kasir);
    BantuanOrganisasi::Masuk($this, $kasir, $k['Tenant']->Id)->post("/kelola/piutang/{$k['P1']->Uuid}/pengingat")->assertForbidden();
});

it('pelanggan tanpa kontak ditolak; piutang tenant lain 404', function (): void {
    $a = SiapkanPiutangPengingat($this, 'Grosir Sembako Solo');
    Pelanggan::query()->whereKey($a['Toko']->Id)->update(['Email' => null, 'NoHp' => '12345']);
    $this->post("/kelola/piutang/{$a['P1']->Uuid}/pengingat")->assertSessionHasErrors('Umum');
    Mail::assertNothingSent();

    $b = SiapkanPiutangPengingat($this, 'Grosir Sembako Klaten');
    // Seperti permintaan sungguhan: konteks tenant belum ada sebelum sesi dikenali.
    app(KonteksTenant::class)->Kosongkan();
    BantuanOrganisasi::Masuk($this, $a['Pemilik'], $a['Tenant']->Id)->post("/kelola/piutang/{$b['P1']->Uuid}/pengingat")->assertNotFound();
});

it('otomatis: H-3 sekali "akan jatuh tempo", H+1 sekali "sudah lewat", tidak dobel; mati = tidak ada; pengaturan diaudit', function (): void {
    $k = SiapkanPiutangPengingat($this);
    $jatuhTempo = CarbonImmutable::parse($k['P1']->JatuhTempo->toDateString(), 'Asia/Jakarta');
    $jalankan = fn () => Artisan::call('pelanggan:kirim-pengingat-piutang', ['--tenant' => [$k['Tenant']->Id]]);

    // Bawaan mati.
    $this->travelTo($jatuhTempo->subDays(3)->setTime(9, 0));
    $jalankan();
    Mail::assertNothingSent();

    BantuanOrganisasi::Masuk($this, $k['Pemilik'], $k['Tenant']->Id)
        ->put('/kelola/piutang/pengingat-otomatis', ['Aktif' => true, 'HariSebelum' => 3, 'IngatkanSaatLewat' => true])
        ->assertSessionHas('Kilat', 'Pengingat piutang otomatis aktif.');
    $this->put('/kelola/piutang/pengingat-otomatis', ['Aktif' => true, 'HariSebelum' => 30, 'IngatkanSaatLewat' => true])->assertSessionHasErrors('HariSebelum');

    // H-4: belum; H-3: dua nota diingatkan; jalan ulang tidak dobel.
    $this->travelTo($jatuhTempo->subDays(4)->setTime(9, 0));
    $jalankan();
    Mail::assertNothingSent();
    $this->travelTo($jatuhTempo->subDays(3)->setTime(9, 0));
    expect($jalankan())->toBe(0);
    $jalankan();
    Mail::assertSent(PengingatPiutangPelanggan::class, 2);
    Mail::assertSent(PengingatPiutangPelanggan::class, fn (PengingatPiutangPelanggan $s): bool => str_contains($s->isi, 'jatuh tempo pada'));

    // H+1: "sudah lewat" sekali lagi per nota.
    $this->travelTo($jatuhTempo->addDay()->setTime(9, 0));
    $jalankan();
    $jalankan();
    Mail::assertSent(PengingatPiutangPelanggan::class, 4);
    Mail::assertSent(PengingatPiutangPelanggan::class, fn (PengingatPiutangPelanggan $s): bool => str_contains($s->isi, 'sudah melewati jatuh tempo'));

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    expect(PengingatPiutang::query()->where('Jenis', JenisPengingatPiutang::SebelumJatuhTempo->value)->count())->toBe(2)
        ->and(PengingatPiutang::query()->where('Jenis', JenisPengingatPiutang::LewatJatuhTempo->value)->count())->toBe(2)
        ->and(LogAudit::query()->where('Peristiwa', 'piutang.pengingat.pengaturan')->count())->toBe(1);
});

it('piutang lunas saat pengingat akan dikirim = dibatalkan, pelanggan tidak ditagih', function (): void {
    $k = SiapkanPiutangPengingat($this);
    Queue::fake();
    $this->post("/kelola/piutang/{$k['P1']->Uuid}/pengingat")->assertSessionHasNoErrors();

    BantuanOrganisasi::AturKonteks($k['Tenant']->Id);
    Piutang::query()->whereKey($k['P1']->Id)->update(['Status' => StatusPiutang::Lunas->value, 'JumlahDibayar' => '77000.00']);
    $pengingat = PengingatPiutang::query()->sole();
    expect(app(PengirimPengingatPiutang::class)->Kirim($pengingat->Id, false))->toBeFalse();

    expect($pengingat->refresh()->Status)->toBe(StatusPengingatPiutang::Dibatalkan);
    Mail::assertNothingSent();
});
