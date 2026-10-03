<?php

declare(strict_types=1);

namespace App\Domain\Tenant\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Aksi\BuatPemilikTenant;
use App\Domain\Organisasi\Aksi\SiapkanOrganisasiAwal;
use App\Domain\Organisasi\Data\DataPemilikBaru;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\PanduanAwal\Aksi\WajibkanPanduanAwal;
use App\Domain\Penjualan\Aksi\SiapkanMetodePembayaranBawaan;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Enum\StatusPaket;
use App\Domain\Tenant\Layanan\PembuatSlugTenant;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\Paket;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * D-35 Edisi Lisensi: satu-satunya usaha di server pembeli, dibuat saat `lisensi:pasang`. Seperti pendaftaran F-00
 * (Owner, outlet & gudang bawaan, metode Tunai, panduan awal wajib) tetapi tanpa persetujuan dokumen legal platform
 * (pembeli sendiri penyelenggaranya) dan langsung berlangganan paket internal `LISENSI` berstatus Aktif tanpa periode
 * berakhir. Fitur & batasnya tidak diambil dari paket ini melainkan dari berkas lisensi (`SumberFiturTenant`); paket
 * hanya memenuhi relasi `Langganan`.
 */
final class SiapkanTenantLisensi
{
    public const KODE_PAKET = 'LISENSI';

    public function __construct(
        private readonly PembuatSlugTenant $pembuatSlug,
        private readonly BuatPemilikTenant $buatPemilik,
        private readonly SiapkanOrganisasiAwal $siapkanOrganisasi,
        private readonly SiapkanMetodePembayaranBawaan $siapkanMetodePembayaran,
        private readonly WajibkanPanduanAwal $wajibkanPanduanAwal,
        private readonly PencatatAudit $pencatatAudit,
    ) {}

    /**
     * @return array{Tenant: Tenant, Pengguna: Pengguna}
     */
    public function Jalankan(string $namaUsaha, DataPemilikBaru $pemilik): array
    {
        return DB::transaction(function () use ($namaUsaha, $pemilik): array {
            // Satu lisensi = satu usaha (D-35). Dikunci agar dua pemasangan bersamaan tidak sama-sama lolos.
            if (Tenant::query()->lockForUpdate()->exists()) {
                throw new PelanggaranAturanBisnis('D-35', 'Server ini sudah punya usaha. Satu lisensi hanya untuk satu usaha.');
            }

            $paket = Paket::query()->firstOrCreate(['Kode' => self::KODE_PAKET], [
                'Nama' => 'Lisensi',
                'Keterangan' => 'Paket internal edisi Lisensi (D-35). Fitur & batas mengikuti berkas lisensi.',
                'Status' => StatusPaket::Diarsipkan,
            ]);

            $tenant = Tenant::query()->create([
                'Nama' => $namaUsaha,
                'Slug' => $this->pembuatSlug->Buat($namaUsaha),
            ]);
            $pengguna = $this->buatPemilik->Jalankan($tenant->Id, $pemilik, emailTerverifikasi: true);

            Langganan::query()->create([
                'IdTenant' => $tenant->Id,
                'IdPaket' => $paket->Id,
                'Status' => StatusLangganan::Aktif,
                'PeriodeMulai' => now(),
            ]);

            $this->siapkanOrganisasi->Jalankan($tenant->Id, $tenant->Nama, $tenant->ZonaWaktu);
            $this->siapkanMetodePembayaran->Jalankan($tenant->Id);
            $this->wajibkanPanduanAwal->Jalankan($tenant->Id);
            $this->pencatatAudit->CatatSesi('tenant.daftar', $tenant->Id, $pengguna->Id, null, null, ['Nama' => $tenant->Nama, 'Paket' => self::KODE_PAKET]);

            return ['Tenant' => $tenant, 'Pengguna' => $pengguna];
        });
    }
}
