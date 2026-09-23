<?php

declare(strict_types=1);

use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Http\Perantara\Pengelola\SesiPengelola;
use Database\Pabrik\PenggunaPengelolaPabrik;
use Inertia\Testing\AssertableInertia;
use PragmaRX\Google2FA\Google2FA;
use Tests\Pendukung\Pengelola\BantuanPengelola;

describe('2FA wajib Platform Pengelola (P-01 langkah 4, BR-P01.2)', function (): void {
    it('AC: anggota yang belum mengaktifkan 2FA diarahkan ke aktivasi dan menu ditolak', function (): void {
        /** @var PenggunaPengelolaPabrik $pabrik */
        $pabrik = PenggunaPengelola::factory();
        $pengguna = $pabrik->DenganPeran(PeranPengelolaBawaan::SuperAdmin)->create();

        $this->actingAs($pengguna, 'pengelola')->withSession([SesiPengelola::TERAKHIR_AKTIF => now()->getTimestamp()]);

        foreach (['/', '/tim-internal', '/log-audit'] as $menu) {
            $this->get(BantuanPengelola::Url($menu))->assertRedirect(route('pengelola.dua-faktor.aktifkan'));
        }

        $this->post(BantuanPengelola::Url('/tim-internal/undangan'), ['Email' => 'baru@contoh.id', 'KodePeran' => ['Analis']])
            ->assertRedirect(route('pengelola.dua-faktor.aktifkan'));
        $this->assertDatabaseCount('UndanganPengelola', 0);
    });

    it('mengaktifkan 2FA dengan kode yang benar lalu menampilkan kode pemulihan sekali', function (): void {
        /** @var PenggunaPengelolaPabrik $pabrik */
        $pabrik = PenggunaPengelola::factory();
        $pengguna = $pabrik->DenganPeran(PeranPengelolaBawaan::SuperAdmin)->create();
        $this->actingAs($pengguna, 'pengelola')->withSession([SesiPengelola::TERAKHIR_AKTIF => now()->getTimestamp()]);

        $this->get(BantuanPengelola::Url('/dua-faktor/aktifkan'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $halaman) => $halaman
                ->component('Pengelola/DuaFaktor/Aktifkan')
                ->where('QrSvg', fn (string $svg) => str_contains($svg, '<svg'))
                ->has('Rahasia'));

        $rahasia = session(SesiPengelola::RAHASIA_2FA_SEMENTARA);
        expect($rahasia)->toBeString();

        $this->post(BantuanPengelola::Url('/dua-faktor/aktifkan'), ['Kode' => '000000'])
            ->assertSessionHasErrors('Kode');
        expect($pengguna->refresh()->DuaFaktorAktif())->toBeFalse();

        $this->post(BantuanPengelola::Url('/dua-faktor/aktifkan'), ['Kode' => (new Google2FA)->getCurrentOtp($rahasia)])
            ->assertRedirect(route('pengelola.dua-faktor.kode-pemulihan'));

        $pengguna->refresh();
        expect($pengguna->DuaFaktorAktif())->toBeTrue()
            ->and($pengguna->KodePemulihan2fa)->toHaveCount(8)
            ->and($pengguna->getRawOriginal('Rahasia2fa'))->not->toBe($rahasia);

        $this->get(BantuanPengelola::Url('/dua-faktor/kode-pemulihan'))
            ->assertInertia(fn (AssertableInertia $halaman) => $halaman
                ->component('Pengelola/DuaFaktor/KodePemulihan')
                ->has('KodePemulihan', 8));
        $this->get(BantuanPengelola::Url('/dua-faktor/kode-pemulihan'))->assertRedirect(route('pengelola.beranda'));

        $this->get(BantuanPengelola::Url('/tim-internal'))->assertOk();
        $this->assertDatabaseHas('LogAuditPengelola', ['Aksi' => 'sesi.dua-faktor.aktifkan', 'IdPenggunaPengelola' => $pengguna->Id]);
    });

    it('memverifikasi kode TOTP setiap kali masuk', function (): void {
        $rahasia = (new Google2FA)->generateSecretKey(32);
        /** @var PenggunaPengelolaPabrik $pabrik */
        $pabrik = PenggunaPengelola::factory();
        $pengguna = $pabrik->DenganDuaFaktor($rahasia)->DenganPeran(PeranPengelolaBawaan::SuperAdmin)->create();
        $this->actingAs($pengguna, 'pengelola')->withSession([SesiPengelola::TERAKHIR_AKTIF => now()->getTimestamp()]);

        $this->get(BantuanPengelola::Url('/tim-internal'))->assertRedirect(route('pengelola.dua-faktor.verifikasi'));
        $this->post(BantuanPengelola::Url('/dua-faktor/verifikasi'), ['Kode' => '000000'])->assertSessionHasErrors('Kode');

        $this->post(BantuanPengelola::Url('/dua-faktor/verifikasi'), ['Kode' => (new Google2FA)->getCurrentOtp($rahasia)])
            ->assertRedirect(route('pengelola.beranda'));

        $this->get(BantuanPengelola::Url('/tim-internal'))->assertOk();
        $this->assertDatabaseHas('LogAuditPengelola', ['Aksi' => 'sesi.masuk', 'IdPenggunaPengelola' => $pengguna->Id]);
    });

    it('menerima kode pemulihan hanya sekali', function (): void {
        $pengguna = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);
        $this->actingAs($pengguna, 'pengelola')->withSession([SesiPengelola::TERAKHIR_AKTIF => now()->getTimestamp()]);

        $this->post(BantuanPengelola::Url('/dua-faktor/verifikasi'), ['Kode' => 'aaaaa-bbbbb'])
            ->assertRedirect(route('pengelola.beranda'));
        expect($pengguna->refresh()->KodePemulihan2fa)->toBe(['CCCCC-DDDDD']);

        $this->flushSession();
        $this->actingAs($pengguna, 'pengelola')->withSession([SesiPengelola::TERAKHIR_AKTIF => now()->getTimestamp()]);
        $this->post(BantuanPengelola::Url('/dua-faktor/verifikasi'), ['Kode' => 'AAAAA-BBBBB'])->assertSessionHasErrors('Kode');
    });

    it('membatasi percobaan kode 2FA', function (): void {
        $pengguna = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);
        $this->actingAs($pengguna, 'pengelola')->withSession([SesiPengelola::TERAKHIR_AKTIF => now()->getTimestamp()]);

        foreach (range(1, 5) as $_) {
            $this->post(BantuanPengelola::Url('/dua-faktor/verifikasi'), ['Kode' => '000000']);
        }

        $this->post(BantuanPengelola::Url('/dua-faktor/verifikasi'), ['Kode' => 'AAAAA-BBBBB'])->assertSessionHasErrors('Kode');
        expect(session('errors')->first('Kode'))->toContain('Terlalu banyak percobaan');
        expect($pengguna->refresh()->KodePemulihan2fa)->toHaveCount(2);
    });
});
