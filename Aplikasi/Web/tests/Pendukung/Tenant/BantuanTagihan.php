<?php

declare(strict_types=1);

namespace Tests\Pendukung\Tenant;

use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Pengelola\Referensi\Aksi\SiapkanPajakBawaan;
use App\Domain\Tenant\Aksi\DaftarkanTenant;
use App\Domain\Tenant\Aksi\UnggahBuktiTransfer;
use App\Domain\Tenant\Data\DataBuktiTransfer;
use App\Domain\Tenant\Model\HargaPaket;
use App\Domain\Tenant\Model\PembayaranLangganan;
use App\Domain\Tenant\Model\TagihanLangganan;
use App\Domain\Tenant\Model\Tenant;
use App\Http\Perantara\IdentifikasiTenantSesi;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Prasyarat P-08 untuk test: paket & harga terbit (P-04), tarif PPN terbit (P-02), rekening tujuan platform.
 * Data master diterbitkan langsung lewat query builder (alur four-eyes-nya diuji di test P-02/P-04).
 */
final class BantuanTagihan
{
    public static function SiapkanPrasyarat(bool $denganPpn = true): void
    {
        BantuanPendaftaran::SiapkanPrasyarat();
        HargaPaket::query()->toBase()->update(['Status' => StatusDataMaster::Terbit->value, 'BerlakuMulai' => '2026-01-01']);

        if ($denganPpn) {
            app(SiapkanPajakBawaan::class)->Jalankan();
            DB::table('TarifPajak')->update(['Status' => StatusDataMaster::Terbit->value, 'BerlakuMulai' => '2025-01-01']);
        }

        config()->set('integrasi.GerbangBilling', [
            'Penyedia' => 'MidtransBilling',
            'Pengaturan' => ['Mode' => 'Sandbox', 'KunciKlien' => 'SB-Mid-client-uji'],
            'Kredensial' => ['KunciServer' => 'SB-Mid-server-kunci-billing-uji'],
        ]);
    }

    /**
     * @return array{Tenant: Tenant, Pengguna: Pengguna}
     */
    public static function DaftarTenant(string $email = 'rina@kopinusantara.id', string $noHp = '081234567890', string $namaUsaha = 'Kopi Nusantara'): array
    {
        return app(DaftarkanTenant::class)->Jalankan(BantuanPendaftaran::Data($email, $noHp, 'PRO', $namaUsaha));
    }

    /** URL absolut back-office tenant: setelah request ke subdomain pengelola, URL relatif ikut host terakhir. */
    public static function Url(string $path): string
    {
        return rtrim((string) config('app.url'), '/').$path;
    }

    /**
     * Masuk back-office sebagai pengguna dengan tenant aktif di sesi. Sesi dikosongkan dulu, seperti login sungguhan:
     * `AuthenticateSession` menolak sesi yang masih membawa jejak pengguna sebelumnya.
     */
    public static function Masuk(TestCase $tes, Pengguna $pengguna, Tenant $tenant): TestCase
    {
        $tes->flushSession();

        return $tes->actingAs($pengguna, 'web')->withSession([IdentifikasiTenantSesi::KUNCI_SESI => $tenant->Id]);
    }

    /** Menjalankan kode domain tenant di luar request (aksi tenant membaca KonteksTenant). */
    public static function AturTenant(Tenant $tenant): void
    {
        app(KonteksTenant::class)->Atur($tenant->Id);
    }

    /**
     * Pembayaran `Menunggu` berupa bukti transfer, lewat Aksi langsung. Halaman tagihan tenant tidak lagi menerima
     * unggahan bukti (tenant membayar lewat gerbang billing), tetapi Aksi & verifikasi platform atas bukti yang sudah
     * masuk tetap ada dan tetap diuji dari sini.
     */
    public static function UnggahBuktiLangsung(Tenant $tenant, Pengguna $pemilik, TagihanLangganan $tagihan, ?string $tanggalTransfer = null): PembayaranLangganan
    {
        config()->set('tagihan.RekeningTujuan', [[
            'Kode' => 'UTAMA',
            'NamaBank' => 'Bank Central Asia',
            'NomorRekening' => '1234567890',
            'AtasNama' => 'PT Kasir Nusantara Digital',
        ]]);
        self::AturTenant($tenant);

        try {
            return app(UnggahBuktiTransfer::class)->Jalankan(
                $tagihan->Uuid,
                new DataBuktiTransfer(
                    UploadedFile::fake()->image('mutasi.png'),
                    $tagihan->Total,
                    CarbonImmutable::parse($tanggalTransfer ?? now('Asia/Jakarta')->toDateString()),
                    'Bank Mandiri',
                    'Rina Wulandari',
                    'UTAMA',
                ),
                $pemilik->Id,
                $pemilik->Nama,
                (string) $pemilik->Email,
            );
        } finally {
            app(KonteksTenant::class)->Kosongkan();
        }
    }
}
