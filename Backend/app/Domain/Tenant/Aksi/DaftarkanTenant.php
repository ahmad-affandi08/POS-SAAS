<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Aksi\BuatPemilikTenant;
use App\Domain\Organisasi\Aksi\SiapkanOrganisasiAwal;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Tenant\Data\DataPendaftaran;
use App\Domain\Tenant\Enum\JenisDokumenLegal;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Enum\StatusPaket;
use App\Domain\Tenant\Kueri\DokumenLegalBerlaku;
use App\Domain\Tenant\Layanan\PembuatSlugTenant;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\Paket;
use App\Domain\Tenant\Model\PersetujuanDokumenLegal;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Pendaftaran tenant baru (F-00 langkah 1 & 3). Dalam satu transaksi: tenant, Owner, langganan trial, persetujuan
 * S&K dan Kebijakan Privasi versi yang berlaku (BR-P06.5), serta outlet & gudang bawaan. Verifikasi email dikirim
 * pemanggil setelah transaksi selesai (BR-00.5).
 */
final class DaftarkanTenant
{
    public function __construct(
        private readonly DokumenLegalBerlaku $dokumenLegalBerlaku,
        private readonly PembuatSlugTenant $pembuatSlug,
        private readonly BuatPemilikTenant $buatPemilik,
        private readonly SiapkanOrganisasiAwal $siapkanOrganisasi,
    ) {}

    public const PERCOBAAN_SLUG = 3;

    /**
     * @return array{Tenant: Tenant, Pengguna: Pengguna}
     */
    public function Jalankan(DataPendaftaran $data): array
    {
        for ($percobaan = 1; ; $percobaan++) {
            try {
                return $this->Simpan($data);
            } catch (UniqueConstraintViolationException $galat) {
                // Slug diperiksa tanpa kunci; pendaftar lain dengan nama usaha sama bisa menang duluan → buat slug baru.
                if (str_contains($galat->getMessage(), 'UniqTenantSlug') && $percobaan < self::PERCOBAAN_SLUG) {
                    continue;
                }

                if (str_contains($galat->getMessage(), 'UniqTenantSlug')) {
                    throw new PelanggaranAturanBisnis('BR-00.2', 'Banyak pendaftaran sedang diproses. Coba kirim lagi.');
                }

                // Pendaftaran bersamaan dengan email/nomor yang sama: yang kalah diminta mencoba lagi.
                throw new PelanggaranAturanBisnis('BR-00.1', 'Email atau nomor WhatsApp ini baru saja didaftarkan. Coba masuk, atau daftar dengan data lain.', 'Email');
            }
        }
    }

    /**
     * @return array{Tenant: Tenant, Pengguna: Pengguna}
     */
    private function Simpan(DataPendaftaran $data): array
    {
        return DB::transaction(function () use ($data): array {
            $dokumen = $this->AmbilDokumenWajib();
            $paket = $this->TentukanPaket($data->kodePaket);

            $tenant = Tenant::query()->create([
                'Nama' => $data->namaUsaha,
                'Slug' => $this->pembuatSlug->Buat($data->namaUsaha),
            ]);
            $pengguna = $this->buatPemilik->Jalankan($tenant->Id, $data->pemilik);
            $sekarang = now();
            $hariTrial = $paket->MasaTrialHari;

            Langganan::query()->create([
                'IdTenant' => $tenant->Id,
                'IdPaket' => $paket->Id,
                'Status' => $hariTrial > 0 ? StatusLangganan::Trial : StatusLangganan::Gratis,
                'TrialBerakhirPada' => $hariTrial > 0 ? $sekarang->copy()->addDays($hariTrial) : null,
                'PeriodeMulai' => $sekarang,
            ]);

            foreach ($dokumen as $dokumenLegal) {
                PersetujuanDokumenLegal::query()->create([
                    'IdDokumenLegal' => $dokumenLegal,
                    'IdTenant' => $tenant->Id,
                    'IdPengguna' => $pengguna->Id,
                    'DisetujuiPada' => $sekarang,
                    'Ip' => $data->ip,
                ]);
            }

            $this->siapkanOrganisasi->Jalankan($tenant->Id, $tenant->Nama, $tenant->ZonaWaktu);

            return ['Tenant' => $tenant, 'Pengguna' => $pengguna];
        });
    }

    /**
     * BR-P06.2: S&K dan Kebijakan Privasi harus sudah berlaku; versi yang berlaku itulah yang disetujui.
     *
     * @return list<int>
     */
    private function AmbilDokumenWajib(): array
    {
        $id = [];

        foreach (JenisDokumenLegal::AmbilWajibRegistrasi() as $jenis) {
            $dokumen = $this->dokumenLegalBerlaku->Cari($jenis, now());

            if ($dokumen === null) {
                throw new PelanggaranAturanBisnis('BR-P06.2', 'Pendaftaran belum dibuka. Silakan coba lagi nanti.');
            }

            $id[] = $dokumen->Id;
        }

        return $id;
    }

    /** BR-00.6: paket pilihan bila aktif & bukan negosiasi, selain itu paket bawaan registrasi. */
    private function TentukanPaket(?string $kodePaket): Paket
    {
        $kandidat = array_values(array_filter([$kodePaket, (string) config('tenant.KodePaketTrialBawaan')]));

        foreach ($kandidat as $kode) {
            $paket = Paket::query()
                ->where('Kode', $kode)
                ->where('Status', StatusPaket::Aktif->value)
                ->where('HargaNegosiasi', false)
                ->first();

            if ($paket !== null) {
                return $paket;
            }
        }

        throw new PelanggaranAturanBisnis('PaketTidakTersedia', 'Pendaftaran belum dibuka. Silakan coba lagi nanti.');
    }
}
