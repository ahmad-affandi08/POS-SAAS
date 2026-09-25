<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Organisasi\Data\DataPemilikBaru;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Pengelola\Katalog\Aksi\AjukanHargaPaket;
use App\Domain\Pengelola\Katalog\Aksi\TinjauHargaPaket;
use App\Domain\Pengelola\Katalog\Aksi\UbahStatusPaket;
use App\Domain\Pengelola\Konten\Aksi\SimpanDrafDokumenLegal;
use App\Domain\Pengelola\Konten\Aksi\TerbitkanDokumenLegal;
use App\Domain\Pengelola\Konten\Data\DataDokumenLegal;
use App\Domain\Pengelola\Referensi\Enum\KeputusanTinjauan;
use App\Domain\Pengelola\TimInternal\Aksi\BuatSuperAdmin;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Aksi\DaftarkanTenant;
use App\Domain\Tenant\Data\DataPendaftaran;
use App\Domain\Tenant\Enum\JenisDokumenLegal;
use App\Domain\Tenant\Enum\StatusDokumenLegal;
use App\Domain\Tenant\Enum\StatusPaket;
use App\Domain\Tenant\Model\DokumenLegal;
use App\Domain\Tenant\Model\HargaPaket;
use App\Domain\Tenant\Model\Paket;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Data demo lingkungan lokal: dua Super Admin (P-01, BR-P01.1), dokumen legal wajib registrasi (P-06, BR-P06.2),
 * paket Gratis & Pro aktif lewat alur tinjauan harga (P-04, BR-P04.5, BR-P04.6), dan satu tenant trial (F-00).
 *
 * Nama kelas mengikuti konvensi Indonesia; `run()` adalah metode framework (§13.7.4).
 * Dilarang jalan di produksi: isinya akun contoh, bukan data nyata.
 *
 * Jalankan: php artisan db:seed --class=DataDemoLokal
 * Kata sandi diambil dari env DEMO_KATA_SANDI; bila kosong dibuat acak dan dicetak sekali ke layar.
 */
final class DataDemoLokal extends Seeder
{
    private const EMAIL_SUPER_ADMIN_SATU = 'admin@payou.test';

    private const EMAIL_SUPER_ADMIN_DUA = 'admin2@payou.test';

    private const EMAIL_PEMILIK = 'owner@payou.test';

    private const NAMA_USAHA = 'Toko Demo PAYOU';

    private const KODE_PAKET = 'PRO';

    /** @var list<string> */
    private array $ringkasan = [];

    public function run(
        BuatSuperAdmin $buatSuperAdmin,
        SimpanDrafDokumenLegal $simpanDraf,
        TerbitkanDokumenLegal $terbitkanDokumen,
        AjukanHargaPaket $ajukanHarga,
        TinjauHargaPaket $tinjauHarga,
        UbahStatusPaket $ubahStatusPaket,
        DaftarkanTenant $daftarkanTenant,
    ): void {
        if (app()->isProduction()) {
            throw new RuntimeException('DataDemoLokal hanya untuk lingkungan non-produksi.');
        }

        $kataSandi = $this->AmbilKataSandi();

        $superAdminSatu = $this->SiapkanSuperAdmin($buatSuperAdmin, self::EMAIL_SUPER_ADMIN_SATU, 'Super Admin PAYOU', $kataSandi);
        $superAdminDua = $this->SiapkanSuperAdmin($buatSuperAdmin, self::EMAIL_SUPER_ADMIN_DUA, 'Admin Kedua PAYOU', $kataSandi);

        $this->TerbitkanDokumenWajib($simpanDraf, $terbitkanDokumen, $superAdminSatu);
        $this->AktifkanPaket($ajukanHarga, $tinjauHarga, $ubahStatusPaket, $superAdminSatu, $superAdminDua);
        $this->DaftarkanTenantDemo($daftarkanTenant, $kataSandi);

        foreach ($this->ringkasan as $baris) {
            $this->command?->info($baris);
        }

        $this->command?->warn("Kata sandi semua akun demo: {$kataSandi}");
        $this->command?->warn('Ganti kata sandi ini sebelum dipakai di lingkungan yang bisa diakses orang lain.');
    }

    private function AmbilKataSandi(): string
    {
        // getenv(), bukan env(): nilai hanya dipakai saat perintah seeder berjalan dan tidak boleh ikut ter-cache config.
        $dariLingkungan = getenv('DEMO_KATA_SANDI');

        if (is_string($dariLingkungan) && $dariLingkungan !== '') {
            return $dariLingkungan;
        }

        // Minimal 12 karakter, huruf dan angka (syarat BuatSuperAdmin).
        return 'Demo'.bin2hex(random_bytes(5)).random_int(10, 99);
    }

    private function SiapkanSuperAdmin(BuatSuperAdmin $buatSuperAdmin, string $email, string $nama, string $kataSandi): PenggunaPengelola
    {
        $adaSebelumnya = PenggunaPengelola::query()->where('Email', $email)->first();

        if ($adaSebelumnya !== null) {
            $this->ringkasan[] = "Super Admin {$email} sudah ada; kata sandi tidak diubah.";

            return $adaSebelumnya;
        }

        $pengguna = $buatSuperAdmin->Jalankan($nama, $email, $kataSandi);
        $this->ringkasan[] = "Super Admin {$email} dibuat.";

        return $pengguna;
    }

    private function TerbitkanDokumenWajib(
        SimpanDrafDokumenLegal $simpanDraf,
        TerbitkanDokumenLegal $terbitkanDokumen,
        PenggunaPengelola $pelaku,
    ): void {
        $hariIni = now('Asia/Jakarta')->toDateString();

        foreach (JenisDokumenLegal::AmbilWajibRegistrasi() as $jenis) {
            $sudahBerlaku = DokumenLegal::query()
                ->where('Jenis', $jenis->value)
                ->where('Status', StatusDokumenLegal::Terbit->value)
                ->whereDate('BerlakuMulai', '<=', $hariIni)
                ->exists();

            if ($sudahBerlaku) {
                $this->ringkasan[] = "{$jenis->AmbilLabel()} sudah berlaku.";

                continue;
            }

            $draf = DokumenLegal::query()
                ->where('Jenis', $jenis->value)
                ->where('Status', StatusDokumenLegal::Draf->value)
                ->first();

            $data = new DataDokumenLegal(
                jenis: $jenis,
                judul: $jenis->AmbilLabel(),
                isi: "# {$jenis->AmbilLabel()}\n\nNaskah contoh untuk lingkungan demo lokal PAYOU. Bukan dokumen legal final.",
                ringkasanPerubahan: null,
                materiil: false,
                berlakuMulai: $hariIni,
            );

            $draf = $simpanDraf->Jalankan($pelaku, $data, $draf);
            $terbitkanDokumen->Jalankan($pelaku, $draf);
            $this->ringkasan[] = "{$jenis->AmbilLabel()} versi {$draf->Versi} diterbitkan.";
        }
    }

    private function AktifkanPaket(
        AjukanHargaPaket $ajukanHarga,
        TinjauHargaPaket $tinjauHarga,
        UbahStatusPaket $ubahStatusPaket,
        PenggunaPengelola $pengaju,
        PenggunaPengelola $peninjau,
    ): void {
        // Gratis dipakai saat trial berakhir (BR-00.3), Pro adalah paket trial bawaan.
        foreach (['GRATIS', self::KODE_PAKET] as $kode) {
            $paket = Paket::query()->where('Kode', $kode)->first();

            if ($paket === null) {
                $this->ringkasan[] = "Paket {$kode} tidak ada; lewati.";

                continue;
            }

            if ($paket->Status === StatusPaket::Aktif) {
                $this->ringkasan[] = "Paket {$kode} sudah aktif.";

                continue;
            }

            $harga = HargaPaket::query()
                ->where('IdPaket', $paket->Id)
                ->where('Status', StatusDataMaster::Draf->value)
                ->orderByDesc('Id')
                ->first();

            if ($harga !== null) {
                $harga->update(['BerlakuMulai' => now('Asia/Jakarta')->toDateString()]);
                $ajukanHarga->Jalankan($pengaju, $harga);
                // BR-P04.5: penyetuju wajib berbeda dari penyusun/pengaju.
                $tinjauHarga->Jalankan($peninjau, $harga, KeputusanTinjauan::Setuju, 'Disetujui untuk lingkungan demo lokal.');
            }

            $ubahStatusPaket->Jalankan($pengaju, $paket->refresh(), StatusPaket::Aktif);
            $this->ringkasan[] = "Paket {$kode} aktif.";
        }
    }

    private function DaftarkanTenantDemo(DaftarkanTenant $daftarkanTenant, string $kataSandi): void
    {
        if (Pengguna::query()->where('Email', self::EMAIL_PEMILIK)->exists()) {
            $this->ringkasan[] = 'Tenant demo sudah ada; lewati pendaftaran.';

            return;
        }

        $hasil = $daftarkanTenant->Jalankan(new DataPendaftaran(
            pemilik: new DataPemilikBaru(
                nama: 'Pemilik Toko Demo',
                email: self::EMAIL_PEMILIK,
                noHp: '081234567890',
                kataSandi: $kataSandi,
            ),
            namaUsaha: self::NAMA_USAHA,
            kodePaket: self::KODE_PAKET,
            ip: '127.0.0.1',
        ));

        $this->ringkasan[] = "Tenant {$hasil['Tenant']->Nama} dibuat dengan Owner ".self::EMAIL_PEMILIK.'.';
    }
}
