<?php

declare(strict_types=1);

use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Referensi\Model\ReferensiBank;
use App\Domain\Referensi\Model\SatuanStandar;
use Inertia\Testing\AssertableInertia;
use Tests\Pendukung\Pengelola\BantuanPengelola;

describe('Referensi pembayaran & satuan standar (P-02)', function (): void {
    it('menambah lalu menonaktifkan referensi bank tanpa menghapus', function (): void {
        $konten = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        $this->actingAs($konten, 'pengelola')->withSession(BantuanPengelola::SesiTerverifikasi());

        $this->post(BantuanPengelola::Url('/referensi/bank'), ['Kode' => 'BCA', 'Nama' => 'Bank Central Asia', 'Jenis' => 'Bank', 'Aktif' => true])
            ->assertSessionHasNoErrors();
        $this->put(BantuanPengelola::Url('/referensi/bank/BCA'), ['Kode' => 'BCA', 'Nama' => 'Bank Central Asia', 'Jenis' => 'Bank', 'Aktif' => false])
            ->assertSessionHasNoErrors();

        expect(ReferensiBank::query()->where('Kode', 'BCA')->sole()->Aktif)->toBeFalse();
        $this->assertDatabaseHas('LogAuditPengelola', ['Aksi' => 'referensi.bank.buat']);
        $this->assertDatabaseHas('LogAuditPengelola', ['Aksi' => 'referensi.bank.ubah']);
    });

    it('menolak kode referensi bank yang tidak sesuai format atau ganda', function (): void {
        $konten = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        ReferensiBank::query()->create(['Kode' => 'BCA', 'Nama' => 'BCA', 'Jenis' => 'Bank']);
        $this->actingAs($konten, 'pengelola')->withSession(BantuanPengelola::SesiTerverifikasi());

        $this->post(BantuanPengelola::Url('/referensi/bank'), ['Kode' => 'bca lama', 'Nama' => 'X', 'Jenis' => 'Bank', 'Aktif' => true])
            ->assertSessionHasErrors('Kode');
        $this->post(BantuanPengelola::Url('/referensi/bank'), ['Kode' => 'BCA', 'Nama' => 'X', 'Jenis' => 'Bank', 'Aktif' => true])
            ->assertSessionHasErrors('Kode');
        $this->post(BantuanPengelola::Url('/referensi/bank'), ['Kode' => 'OVO', 'Nama' => 'OVO', 'Jenis' => 'Kripto', 'Aktif' => true])
            ->assertSessionHasErrors('Jenis');
    });

    it('seeder menyiapkan satuan standar awal secara idempoten', function (): void {
        $this->seed();
        $this->seed();

        expect(SatuanStandar::query()->whereIn('Kode', ['PCS', 'KG', 'L', 'M', 'DUS'])->count())->toBe(5)
            ->and(SatuanStandar::query()->where('Kode', 'KG')->sole()->BolehDesimal)->toBeTrue()
            ->and(SatuanStandar::query()->where('Kode', 'PCS')->sole()->BolehDesimal)->toBeFalse();
    });

    it('mengelola satuan standar dengan izin, peran lain hanya melihat', function (): void {
        $konten = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::KontenLegal);
        $keuangan = BantuanPengelola::BuatAnggota(PeranPengelolaBawaan::Keuangan);

        $this->actingAs($konten, 'pengelola')
            ->withSession(BantuanPengelola::SesiTerverifikasi())
            ->post(BantuanPengelola::Url('/referensi/satuan'), ['Kode' => 'RIM', 'Nama' => 'Rim', 'Simbol' => 'rim', 'BolehDesimal' => false, 'Aktif' => true])
            ->assertSessionHasNoErrors();

        $this->actingAs($keuangan, 'pengelola')
            ->get(BantuanPengelola::Url('/referensi/satuan'))
            ->assertInertia(fn (AssertableInertia $halaman) => $halaman->component('Pengelola/Referensi/Satuan')->has('Satuan', 1));
        $this->put(BantuanPengelola::Url('/referensi/satuan/RIM'), ['Kode' => 'RIM', 'Nama' => 'Rim kertas', 'Simbol' => 'rim', 'BolehDesimal' => false, 'Aktif' => true])
            ->assertForbidden();

        expect(SatuanStandar::query()->where('Kode', 'RIM')->sole()->Nama)->toBe('Rim');
    });
});
