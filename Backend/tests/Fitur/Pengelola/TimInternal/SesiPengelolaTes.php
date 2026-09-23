<?php

declare(strict_types=1);

use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Database\Pabrik\PenggunaPengelolaPabrik;
use Tests\Pendukung\Pengelola\BantuanPengelola;

describe('Masuk Platform Pengelola & pemisahan sesi (BR-P01.2, BR-P01.4)', function (): void {
    it('masuk dengan kata sandi lalu diarahkan ke verifikasi 2FA, memakai cookie sesi pengelola', function (): void {
        $pengguna = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);

        $respons = $this->post(BantuanPengelola::Url('/masuk'), [
            'Email' => $pengguna->Email,
            'KataSandi' => PenggunaPengelolaPabrik::KATA_SANDI,
        ]);

        $respons->assertRedirect(route('pengelola.beranda'))
            ->assertCookie((string) config('pengelola.CookieSesi'))
            ->assertCookieMissing((string) config('session.cookie'));
        $this->assertAuthenticatedAs($pengguna, 'pengelola');
        $this->assertGuest('web');
        expect($pengguna->refresh()->TerakhirMasukPada)->not->toBeNull();

        $this->get(BantuanPengelola::Url('/'))->assertRedirect(route('pengelola.dua-faktor.verifikasi'));
    });

    it('menolak kata sandi salah dan akun nonaktif dengan pesan yang sama', function (): void {
        $aktif = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Dukungan);
        $nonaktif = PenggunaPengelola::factory()->Nonaktif()->create();

        $this->from(BantuanPengelola::Url('/masuk'))
            ->post(BantuanPengelola::Url('/masuk'), ['Email' => $aktif->Email, 'KataSandi' => 'salah-sandi-123'])
            ->assertSessionHasErrors(['Email' => 'Email atau kata sandi salah.']);

        $this->from(BantuanPengelola::Url('/masuk'))
            ->post(BantuanPengelola::Url('/masuk'), ['Email' => $nonaktif->Email, 'KataSandi' => PenggunaPengelolaPabrik::KATA_SANDI])
            ->assertSessionHasErrors(['Email' => 'Email atau kata sandi salah.']);

        $this->assertGuest('pengelola');
    });

    it('membatasi percobaan masuk setelah 5 kali gagal', function (): void {
        $pengguna = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Dukungan);

        foreach (range(1, 5) as $_) {
            $this->post(BantuanPengelola::Url('/masuk'), ['Email' => $pengguna->Email, 'KataSandi' => 'salah-sandi-123']);
        }

        $this->post(BantuanPengelola::Url('/masuk'), ['Email' => $pengguna->Email, 'KataSandi' => PenggunaPengelolaPabrik::KATA_SANDI])
            ->assertSessionHasErrors('Email');
        $this->assertGuest('pengelola');
    });

    it('tidak menerima sesi tenant di Platform Pengelola walau emailnya sama', function (): void {
        $pengelola = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);
        $tenant = Pengguna::factory()->create(['Email' => $pengelola->Email]);

        $this->actingAs($tenant, 'web')
            ->get(BantuanPengelola::Url('/'))
            ->assertRedirect(route('pengelola.masuk'));
    });

    it('tidak melayani rute tenant di subdomain pengelola dan sebaliknya', function (): void {
        $this->get(BantuanPengelola::Url('/sembarang-halaman-tenant'))->assertNotFound();
        $this->get('http://localhost/masuk')->assertNotFound();
        $this->get('http://localhost/')->assertOk();
    });

    it('mengakhiri sesi setelah 30 menit tidak aktif', function (): void {
        $pengguna = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);
        $this->actingAs($pengguna, 'pengelola')->withSession(BantuanPengelola::SesiTerverifikasi());

        $this->travel(29)->minutes();
        $this->get(BantuanPengelola::Url('/'))->assertOk();

        $this->travel(31)->minutes();
        $this->get(BantuanPengelola::Url('/'))
            ->assertRedirect(route('pengelola.masuk'))
            ->assertSessionHas('Kilat');
        $this->assertGuest('pengelola');
    });

    it('keluar mencatat audit dan memutus sesi', function (): void {
        $pengguna = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::SuperAdmin);

        $this->actingAs($pengguna, 'pengelola')
            ->withSession(BantuanPengelola::SesiTerverifikasi())
            ->post(BantuanPengelola::Url('/keluar'))
            ->assertRedirect(route('pengelola.masuk'));

        $this->assertGuest('pengelola');
        $this->assertDatabaseHas('LogAuditPengelola', ['Aksi' => 'sesi.keluar', 'IdPenggunaPengelola' => $pengguna->Id]);
    });
});
