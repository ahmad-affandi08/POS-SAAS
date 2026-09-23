<?php

declare(strict_types=1);

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Dukungan\Enum\PrioritasTiketDukungan;
use App\Domain\Dukungan\Enum\StatusTiketDukungan;
use App\Domain\Dukungan\Model\TiketDukungan;
use App\Domain\Pengelola\Bersama\KonteksPengelola;
use App\Domain\Pengelola\Dukungan\Surel\BalasanTiketDukungan;
use App\Domain\Pengelola\TimInternal\Enum\IzinPengelola;
use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Model\LogAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Dukungan\BantuanDukungan;
use Tests\Pendukung\Pengelola\BantuanPengelola;
use Tests\TestCase;

function MasukSebagaiPetugas(TestCase $tes, PenggunaPengelola $pengguna): TestCase
{
    return $tes->actingAs($pengguna, 'pengelola')->withSession(BantuanPengelola::SesiTerverifikasi());
}

beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2026-09-24 09:00:00', 'Asia/Jakarta'));
    Mail::fake();
    Storage::fake('local');
});

describe('Izin tiket dukungan (§19.3)', function (): void {
    it('Dukungan mendapat izin lihat & tangani, Teknis/Keuangan/Analis tidak', function (): void {
        $dukungan = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Dukungan);
        $superAdmin = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);

        expect($dukungan->PunyaIzin(IzinPengelola::DukunganTiketTangani))->toBeTrue()
            ->and($dukungan->PunyaIzin(IzinPengelola::OperasionalLihat))->toBeFalse()
            ->and($superAdmin->PunyaIzin(IzinPengelola::DukunganTiketTangani))->toBeTrue();
    });

    it('peran tanpa izin tiket tidak bisa membuka antrean maupun tiket', function (PeranPengelolaBawaan $peran): void {
        ['Tenant' => $tenant, 'Pengguna' => $pengguna] = BantuanDukungan::BuatTenant();
        $tiket = BantuanDukungan::BuatTiket($tenant, $pengguna);
        MasukSebagaiPetugas($this, BantuanPengelola::BuatAnggota($peran));

        $this->get(BantuanPengelola::Url('/dukungan/tiket'))->assertForbidden();
        $this->get(BantuanPengelola::Url("/dukungan/tiket/{$tiket->Uuid}"))->assertForbidden();
        $this->post(BantuanPengelola::Url("/dukungan/tiket/{$tiket->Uuid}/balasan"), ['Isi' => 'Halo', 'CatatanInternal' => false])->assertForbidden();
    })->with([
        PeranPengelolaBawaan::Teknis, PeranPengelolaBawaan::Keuangan, PeranPengelolaBawaan::Analis,
        PeranPengelolaBawaan::KontenLegal, PeranPengelolaBawaan::MitraPenjualan,
    ]);
});

describe('Antrean lintas tenant lewat KonteksPengelola (§13.8)', function (): void {
    it('menampilkan tiket semua tenant, tersaring status/prioritas/lewat SLA/milik, dan mencatat audit akses', function (): void {
        $petugas = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Dukungan);
        ['Tenant' => $a, 'Pengguna' => $pa] = BantuanDukungan::BuatTenant('Kopi Nusantara', 'rina@kopinusantara.id');
        ['Tenant' => $b, 'Pengguna' => $pb] = BantuanDukungan::BuatTenant('Laundry Bersih', 'budi@laundry.id');
        $lama = BantuanDukungan::BuatTiket($a, $pa, 'Stok minus setelah retur', PrioritasTiketDukungan::Tinggi);
        $this->travel(9)->hours();
        $baru = BantuanDukungan::BuatTiket($b, $pb, 'Nota laundry tidak terkirim ke WhatsApp', PrioritasTiketDukungan::Mendesak);
        MasukSebagaiPetugas($this, $petugas);

        $this->get(BantuanPengelola::Url('/dukungan/tiket'))->assertInertia(fn (AssertableInertia $halaman) => $halaman
            ->component('Pengelola/Dukungan/Antrean')
            ->where('Tiket.Total', 2)
            // Batas SLA terdekat di atas: tiket lama (Tinggi, 8 jam) sudah lewat.
            ->where('Tiket.Data.0.Nomor', $lama->Nomor)
            ->where('Tiket.Data.0.NamaTenant', 'Kopi Nusantara')
            ->where('Tiket.Data.0.LewatSla', true)
            ->where('Tiket.Data.1.NamaTenant', 'Laundry Bersih')
            ->where('Tiket.Data.1.LewatSla', false));

        $this->get(BantuanPengelola::Url('/dukungan/tiket?lewat-sla=1'))
            ->assertInertia(fn (AssertableInertia $halaman) => $halaman->where('Tiket.Total', 1)->where('Tiket.Data.0.Nomor', $lama->Nomor));
        $this->get(BantuanPengelola::Url('/dukungan/tiket?prioritas=Mendesak'))
            ->assertInertia(fn (AssertableInertia $halaman) => $halaman->where('Tiket.Total', 1)->where('Tiket.Data.0.Nomor', $baru->Nomor));
        $this->get(BantuanPengelola::Url('/dukungan/tiket?milik=saya'))
            ->assertInertia(fn (AssertableInertia $halaman) => $halaman->where('Tiket.Total', 0));
        $this->get(BantuanPengelola::Url('/dukungan/tiket?status=Ditutup'))
            ->assertInertia(fn (AssertableInertia $halaman) => $halaman->where('Tiket.Total', 0));
        $this->get(BantuanPengelola::Url('/dukungan/tiket?status=salah'))
            ->assertInertia(fn (AssertableInertia $halaman) => $halaman->where('Tiket.Total', 0));
        $this->get(BantuanPengelola::Url('/dukungan/tiket?kata=laundry'))
            ->assertInertia(fn (AssertableInertia $halaman) => $halaman->where('Tiket.Total', 1));

        $log = LogAuditPengelola::query()->where('Aksi', 'lintas-tenant.akses')->orderBy('Id')->first();
        expect($log?->IdPenggunaPengelola)->toBe($petugas->Id)
            ->and($log?->Alasan)->toBe('Membuka antrean tiket dukungan');
    });

    it('detail tiket memuat pelapor, tenant, catatan internal, dan tercatat dengan tenant tiket', function (): void {
        $petugas = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Dukungan);
        ['Tenant' => $tenant, 'Pengguna' => $pengguna] = BantuanDukungan::BuatTenant();
        $tiket = BantuanDukungan::BuatTiket($tenant, $pengguna);
        MasukSebagaiPetugas($this, $petugas);

        $this->post(BantuanPengelola::Url("/dukungan/tiket/{$tiket->Uuid}/balasan"), ['Isi' => 'Cek firmware printer.', 'CatatanInternal' => true])
            ->assertSessionHasNoErrors();

        $this->get(BantuanPengelola::Url("/dukungan/tiket/{$tiket->Uuid}"))->assertInertia(fn (AssertableInertia $halaman) => $halaman
            ->component('Pengelola/Dukungan/Tiket')
            ->where('Tiket.Nomor', $tiket->Nomor)
            ->where('Tiket.Tenant.Nama', 'Kopi Nusantara')
            ->where('Tiket.Pelapor.Email', 'rina@kopinusantara.id')
            ->has('Tiket.Pesan', 2)
            ->where('Tiket.Pesan.1.CatatanInternal', true)
            ->has('Penangan', 1));

        expect(LogAuditPengelola::query()->where('Aksi', 'lintas-tenant.akses')->where('IdTenant', $tenant->Id)->exists())->toBeTrue()
            // Catatan internal tidak mengubah status, SLA, atau mengirim email ke tenant.
            ->and(BantuanDukungan::MuatUlang($tiket)->Status)->toBe(StatusTiketDukungan::Baru)
            ->and(BantuanDukungan::MuatUlang($tiket)->ResponsPertamaPada)->toBeNull();
        Mail::assertNothingQueued();
    });

    it('KonteksPengelola: kueri lintas tenant hanya di dalam JalankanLintasTenant, alasan wajib, tenant aktif dipulihkan', function (): void {
        $konteks = app(KonteksPengelola::class);
        app(KonteksTenant::class)->Atur(77);

        expect(fn () => $konteks->KueriLintasTenant(TiketDukungan::class))->toThrow(LogicException::class)
            ->and(fn () => $konteks->JalankanLintasTenant('  ', fn () => 1))->toThrow(InvalidArgumentException::class);

        $dalam = $konteks->JalankanLintasTenant('Uji konteks', fn () => app(KonteksTenant::class)->Ambil(), 5);

        expect($dalam)->toBe(5)
            ->and(app(KonteksTenant::class)->Ambil())->toBe(77)
            ->and(LogAuditPengelola::query()->where('Aksi', 'lintas-tenant.akses')->sole()->IdTenant)->toBe(5);
    });
});

describe('Menangani tiket', function (): void {
    it('mengambil tiket: penanggung jawab diri sendiri, status Ditangani, catatan internal & audit', function (): void {
        $petugas = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Dukungan);
        ['Tenant' => $tenant, 'Pengguna' => $pengguna] = BantuanDukungan::BuatTenant();
        $tiket = BantuanDukungan::BuatTiket($tenant, $pengguna);
        MasukSebagaiPetugas($this, $petugas)->post(BantuanPengelola::Url("/dukungan/tiket/{$tiket->Uuid}/ambil"))->assertSessionHasNoErrors();

        $tiket = BantuanDukungan::MuatUlang($tiket);
        expect($tiket->IdPenanggungJawab)->toBe($petugas->Id)
            ->and($tiket->Status)->toBe(StatusTiketDukungan::Ditangani)
            ->and(DB::table('TiketDukunganPesan')->where('CatatanInternal', true)->value('Isi'))->toBe("{$petugas->Nama} mengambil tiket ini.");

        $log = LogAuditPengelola::query()->where('Aksi', 'dukungan.tiket.tugaskan')->sole();
        expect($log->IdTenant)->toBe($tenant->Id)->and($log->NilaiBaru)->toMatchArray(['Status' => 'Ditangani']);
    });

    it('menugaskan hanya ke anggota aktif yang berizin menangani tiket', function (): void {
        $petugas = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);
        $teknis = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Teknis);
        $rekan = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Dukungan);
        ['Tenant' => $tenant, 'Pengguna' => $pengguna] = BantuanDukungan::BuatTenant();
        $tiket = BantuanDukungan::BuatTiket($tenant, $pengguna);
        MasukSebagaiPetugas($this, $petugas);

        $this->post(BantuanPengelola::Url("/dukungan/tiket/{$tiket->Uuid}/tugaskan"), ['UuidPenanggungJawab' => $teknis->Uuid])
            ->assertSessionHasErrors('UuidPenanggungJawab');
        $this->post(BantuanPengelola::Url("/dukungan/tiket/{$tiket->Uuid}/tugaskan"), ['UuidPenanggungJawab' => $rekan->Uuid])
            ->assertSessionHasNoErrors();

        expect(BantuanDukungan::MuatUlang($tiket)->IdPenanggungJawab)->toBe($rekan->Id);
    });

    it('balasan ke tenant mengisi respons pertama, menetapkan penanggung jawab, dan mengantrekan email ke pelapor', function (): void {
        $petugas = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Dukungan);
        ['Tenant' => $tenant, 'Pengguna' => $pengguna] = BantuanDukungan::BuatTenant();
        $tiket = BantuanDukungan::BuatTiket($tenant, $pengguna);
        $this->travel(2)->hours();

        MasukSebagaiPetugas($this, $petugas)->post(BantuanPengelola::Url("/dukungan/tiket/{$tiket->Uuid}/balasan"), [
            'Isi' => 'Silakan perbarui aplikasi kasir ke versi 1.4.2 lalu sambungkan ulang printer.',
            'CatatanInternal' => false,
            'Status' => 'MenungguPelanggan',
            'Lampiran' => [UploadedFile::fake()->create('panduan-printer.pdf', 200, 'application/pdf')],
        ])->assertSessionHasNoErrors();

        $tiket = BantuanDukungan::MuatUlang($tiket);
        expect($tiket->Status)->toBe(StatusTiketDukungan::MenungguPelanggan)
            ->and($tiket->IdPenanggungJawab)->toBe($petugas->Id)
            ->and($tiket->ResponsPertamaPada?->toIso8601String())->toBe(now()->toIso8601String())
            ->and($tiket->CekLewatSla())->toBeFalse();
        Mail::assertQueued(BalasanTiketDukungan::class, fn (BalasanTiketDukungan $surel) => $surel->hasTo('rina@kopinusantara.id')
            && str_contains($surel->isi, 'versi 1.4.2'));
        expect(LogAuditPengelola::query()->where('Aksi', 'dukungan.tiket.balas')->sole()->NilaiBaru)->toMatchArray(['JumlahLampiran' => 1]);

        // Lampiran tim bisa diunduh tenant pemilik tiket.
        $lampiran = json_decode((string) DB::table('TiketDukunganPesan')->whereNotNull('Lampiran')->value('Lampiran'), true)[0];
        // URL absolut: setelah request ke subdomain pengelola, URL relatif test ikut host terakhir.
        BantuanDukungan::MasukSebagaiTenant($this, $pengguna, $tenant)
            ->get(rtrim((string) config('app.url'), '/')."/kelola/bantuan/{$tiket->Uuid}/lampiran/{$lampiran['Uuid']}")
            ->assertDownload('panduan-printer.pdf');
    });

    it('catatan internal tidak boleh sekaligus mengubah status, dan tiket selesai harus dibuka dulu sebelum dibalas', function (): void {
        $petugas = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Dukungan);
        ['Tenant' => $tenant, 'Pengguna' => $pengguna] = BantuanDukungan::BuatTenant();
        $tiket = BantuanDukungan::BuatTiket($tenant, $pengguna);
        MasukSebagaiPetugas($this, $petugas);
        $url = BantuanPengelola::Url("/dukungan/tiket/{$tiket->Uuid}");

        $this->post("{$url}/balasan", ['Isi' => 'Catatan', 'CatatanInternal' => true, 'Status' => 'Selesai'])->assertSessionHasErrors('Status');
        $this->put("{$url}/status", ['Status' => 'Selesai'])->assertSessionHasNoErrors();
        $this->post("{$url}/balasan", ['Isi' => 'Halo lagi', 'CatatanInternal' => false])->assertSessionHasErrors('Isi');
        $this->post("{$url}/balasan", ['Isi' => 'Catatan setelah selesai', 'CatatanInternal' => true])->assertSessionHasNoErrors();
    });

    it('status mengikuti state machine: tutup wajib alasan, Ditutup final, buka lagi hanya dalam 7 hari', function (): void {
        $petugas = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Dukungan);
        ['Tenant' => $tenant, 'Pengguna' => $pengguna] = BantuanDukungan::BuatTenant();
        $tiket = BantuanDukungan::BuatTiket($tenant, $pengguna);
        $lain = BantuanDukungan::BuatTiket($tenant, $pengguna, 'Tiket duplikat');
        MasukSebagaiPetugas($this, $petugas);
        $url = BantuanPengelola::Url("/dukungan/tiket/{$tiket->Uuid}/status");

        $this->put($url, ['Status' => 'Baru'])->assertSessionHasErrors('Status');
        $this->put($url, ['Status' => 'Selesai'])->assertSessionHasNoErrors();
        $this->travel(8)->days();
        // Sesi pengelola berakhir setelah 30 menit tidak aktif (BR-P01.2): masuk lagi.
        MasukSebagaiPetugas($this, $petugas);
        $this->put($url, ['Status' => 'Ditangani'])->assertSessionHasErrors('Status');
        $this->put($url, ['Status' => 'Ditutup'])->assertSessionHasErrors('Alasan');
        $this->put($url, ['Status' => 'Ditutup', 'Alasan' => 'Tidak ada kabar dari pelapor'])->assertSessionHasNoErrors();
        $this->put($url, ['Status' => 'Ditangani'])->assertSessionHasErrors('Status');

        $this->put(BantuanPengelola::Url("/dukungan/tiket/{$lain->Uuid}/status"), ['Status' => 'Ditutup', 'Alasan' => 'Duplikat dari '.$tiket->Nomor])
            ->assertSessionHasNoErrors();

        expect(BantuanDukungan::MuatUlang($tiket)->Status)->toBe(StatusTiketDukungan::Ditutup)
            ->and(BantuanDukungan::MuatUlang($tiket)->DitutupPada)->not->toBeNull()
            ->and(LogAuditPengelola::query()->where('Aksi', 'dukungan.tiket.ubah-status')->count())->toBe(3);
    });

    it('mengubah prioritas menghitung ulang batas SLA selama belum ada respons pertama', function (): void {
        $petugas = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Dukungan);
        ['Tenant' => $tenant, 'Pengguna' => $pengguna] = BantuanDukungan::BuatTenant();
        $tiket = BantuanDukungan::BuatTiket($tenant, $pengguna);
        MasukSebagaiPetugas($this, $petugas)
            ->put(BantuanPengelola::Url("/dukungan/tiket/{$tiket->Uuid}/prioritas"), ['Prioritas' => 'Mendesak'])
            ->assertSessionHasNoErrors();

        $tiket = BantuanDukungan::MuatUlang($tiket);
        expect($tiket->Prioritas)->toBe(PrioritasTiketDukungan::Mendesak)
            ->and($tiket->JamSla)->toBe(4)
            ->and($tiket->BatasSlaPada->toIso8601String())->toBe($tiket->DibuatPada->copy()->addHours(4)->toIso8601String());
    });
});
