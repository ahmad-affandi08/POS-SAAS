<?php

declare(strict_types=1);

namespace Tests\Pendukung\Dukungan;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Dukungan\Aksi\BuatTiketDukungan;
use App\Domain\Dukungan\Data\DataTiketBaru;
use App\Domain\Dukungan\Enum\KategoriTiketDukungan;
use App\Domain\Dukungan\Enum\PrioritasTiketDukungan;
use App\Domain\Dukungan\Model\TiketDukungan;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Tenant\Aksi\DaftarkanTenant;
use App\Domain\Tenant\Model\Paket;
use App\Domain\Tenant\Model\Tenant;
use App\Http\Perantara\IdentifikasiTenantSesi;
use Tests\Pendukung\Tenant\BantuanPendaftaran;
use Tests\TestCase;

/**
 * Bantuan test tiket dukungan (P-09): tenant terdaftar lengkap (F-00) dan tiket yang dibuat lewat aksi tenant.
 */
final class BantuanDukungan
{
    /**
     * @return array{Tenant: Tenant, Pengguna: Pengguna}
     */
    public static function BuatTenant(string $namaUsaha = 'Kopi Nusantara', string $email = 'rina@kopinusantara.id', ?string $kodePaket = null): array
    {
        if (! Paket::query()->where('Kode', 'PRO')->exists()) {
            BantuanPendaftaran::SiapkanPrasyarat();
        }

        $noHp = '08'.substr(str_pad((string) crc32($email), 10, '7'), 0, 10);

        return app(DaftarkanTenant::class)->Jalankan(BantuanPendaftaran::Data($email, $noHp, $kodePaket, $namaUsaha));
    }

    public static function MasukSebagaiTenant(TestCase $tes, Pengguna $pengguna, Tenant $tenant): TestCase
    {
        return $tes->actingAs($pengguna, 'web')->withSession([IdentifikasiTenantSesi::KUNCI_SESI => $tenant->Id]);
    }

    public static function BuatTiket(
        Tenant $tenant,
        Pengguna $pelapor,
        string $judul = 'Printer struk tidak mencetak setelah pembaruan',
        PrioritasTiketDukungan $prioritas = PrioritasTiketDukungan::Normal,
        string $isi = 'Sejak pagi printer Bluetooth di Outlet Utama tidak mencetak struk. Sudah dimatikan dan dinyalakan ulang.',
    ): TiketDukungan {
        $konteks = app(KonteksTenant::class);
        $sebelumnya = $konteks->Ambil();
        $konteks->Atur($tenant->Id);

        try {
            return app(BuatTiketDukungan::class)->Jalankan($pelapor->Id, $pelapor->Nama, new DataTiketBaru(
                kategori: KategoriTiketDukungan::Perangkat,
                prioritas: $prioritas,
                judul: $judul,
                isi: $isi,
            ));
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }
    }

    /** Membaca tiket tanpa batas tenant, khusus untuk assertion test. */
    public static function MuatUlang(TiketDukungan $tiket): TiketDukungan
    {
        $konteks = app(KonteksTenant::class);
        $sebelumnya = $konteks->Ambil();
        $konteks->Atur($tiket->IdTenant);

        try {
            return TiketDukungan::query()->whereKey($tiket->Id)->sole();
        } finally {
            $sebelumnya === null ? $konteks->Kosongkan() : $konteks->Atur($sebelumnya);
        }
    }
}
