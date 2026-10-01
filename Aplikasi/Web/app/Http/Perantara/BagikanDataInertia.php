<?php

declare(strict_types=1);

namespace App\Http\Perantara;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Kueri\KeanggotaanPengguna;
use App\Domain\Organisasi\Kueri\PemilikTenant;
use App\Domain\Organisasi\Kueri\SektorOutletTenant;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Tenant\Enum\PlatformPengumuman;
use App\Domain\Tenant\Kueri\PenawaranFiturTenant;
use App\Domain\Tenant\Kueri\PengumumanBerlaku;
use App\Domain\Tenant\Kueri\PersetujuanLegalTertunda;
use App\Domain\Tenant\Kueri\RingkasanLanggananTenant;
use App\Domain\Tenant\Kueri\RingkasanTenant;
use App\Domain\Tenant\Model\DokumenLegal;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;

/**
 * Perantara Inertia: menentukan view root dan data yang dibagikan ke semua halaman (PRD §13.5).
 */
final class BagikanDataInertia extends Middleware
{
    protected $rootView = 'Aplikasi';

    public function __construct(
        private readonly RingkasanTenant $ringkasanTenant,
        private readonly KeanggotaanPengguna $keanggotaan,
        // BR-P06.5: banner pengumuman versi materiil dokumen legal.
        private readonly PemilikTenant $pemilikTenant,
        private readonly PersetujuanLegalTertunda $persetujuanLegal,
        // F-00: banner status langganan Tertunggak/Ditangguhkan.
        private readonly RingkasanLanggananTenant $ringkasanLangganan,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $pengguna = $request->user('web');
        $idTenant = $request->session()->get(IdentifikasiTenantSesi::KUNCI_SESI);

        return [
            ...parent::share($request),
            'NamaAplikasi' => config('app.name'),
            // D-20: logo di halaman masuk/daftar menuju situs pemasaran (bisa host lain).
            'UrlPemasaran' => ArahkanDomainAplikasi::BuatUrlPemasaran('/'),
            'Kilat' => fn () => $request->session()->get('Kilat'),
            'Pengguna' => fn () => $pengguna instanceof Pengguna ? [
                'Uuid' => $pengguna->Uuid,
                'Nama' => $pengguna->Nama,
                'Email' => $pengguna->Email,
                // BR-00.5: banner pengingat selama email belum terverifikasi.
                'EmailTerverifikasi' => $pengguna->EmailDiverifikasiPada !== null,
            ] : null,
            // Nama & status langganan tenant aktif hanya bila pengguna masih anggotanya (F-00: banner Tertunggak/Ditangguhkan).
            'TenantAktif' => function () use ($pengguna, $idTenant): ?array {
                $anggota = $pengguna instanceof Pengguna && is_int($idTenant) && $this->keanggotaan->CekAnggota($pengguna->Id, $idTenant);
                $tenant = $anggota && is_int($idTenant) ? ($this->ringkasanTenant->Ambil([$idTenant])[0] ?? null) : null;

                if ($tenant === null) {
                    return null;
                }

                $langganan = $this->ringkasanLangganan->Ambil($tenant['Id']);
                $pathLogo = $tenant['PathLogo'] ?? null;
                $tautanLogo = $pathLogo !== null
                    ? route('kelola.pengaturan.profil-usaha.logo', ['v' => substr(hash('sha256', (string) $pathLogo), 0, 12)])
                    : null;

                return [
                    'Id' => $tenant['Id'],
                    'KodePelanggan' => sprintf('#%07d', $tenant['Id']),
                    'Nama' => $tenant['Nama'],
                    'Slug' => $tenant['Slug'],
                    'TautanLogo' => $tautanLogo,
                    'NamaPaket' => $langganan['NamaPaket'] ?? 'Dasar',
                    'KodePaket' => $langganan['KodePaket'] ?? '',
                    'StatusLangganan' => $langganan === null ? null : $langganan['Status']->value,
                    'PeriodeSelesai' => $langganan === null ? null : $langganan['PeriodeSelesai']?->toIso8601ZuluString(),
                    'BatasTenggangPada' => $langganan === null ? null : $langganan['BatasTenggangPada']?->toIso8601ZuluString(),
                    'TagihanTertunda' => $langganan['TagihanTertunda'] ?? null,
                ];
            },
            // BR-P06.5: banner di back-office selama masa pengumuman versi materiil, hanya untuk Owner tenant aktif.
            'PengumumanLegal' => function () use ($pengguna, $idTenant): array {
                if (! $pengguna instanceof Pengguna || ! is_int($idTenant) || ! $this->pemilikTenant->CekPemilik($pengguna->Id, $idTenant)) {
                    return [];
                }

                return array_map(fn (DokumenLegal $dokumen): array => [
                    'Label' => $dokumen->Jenis->AmbilLabel(),
                    'Versi' => $dokumen->Versi,
                    'BerlakuMulai' => $dokumen->BerlakuMulai->toDateString(),
                    'Tautan' => route('legal.tampil', ['jenis' => Str::kebab($dokumen->Jenis->value), 'versi' => $dokumen->Versi]),
                ], $this->persetujuanLegal->AmbilPengumuman(now()));
            },
            // F-02: hak akses di tenant aktif untuk menu & tombol (hanya UX; server tetap penentu lewat WajibIzinTenant).
            'Akses' => function () use ($pengguna): ?array {
                $idTenant = app(KonteksTenant::class)->Ambil();
                $akses = $pengguna instanceof Pengguna && $idTenant !== null ? app(AksesPengguna::class)->Ambil($idTenant, $pengguna->Id) : null;

                return $akses === null ? null : ['Pemilik' => $akses['Pemilik'], 'Izin' => $akses['Izin']];
            },
            // D-23: fitur di luar paket tetap tampil di menu; klik = dialog naik paket / add-on (hanya UX).
            'FiturPaket' => function () use ($pengguna): ?array {
                $idTenant = app(KonteksTenant::class)->Ambil();

                return $pengguna instanceof Pengguna && $idTenant !== null ? app(PenawaranFiturTenant::class)->Ambil($idTenant) : null;
            },
            // P-10 PGL-19: pengumuman & banner pemeliharaan platform untuk paket & sektor tenant aktif.
            'PengumumanPlatform' => function () use ($pengguna): array {
                $idTenant = app(KonteksTenant::class)->Ambil();

                if (! $pengguna instanceof Pengguna || $idTenant === null) {
                    return [];
                }

                $kodePaket = $this->ringkasanLangganan->Ambil($idTenant)['KodePaket'] ?? null;

                return app(PengumumanBerlaku::class)->AmbilUntuk(PlatformPengumuman::Web, $kodePaket, app(SektorOutletTenant::class)->AmbilKode(), null);
            },
        ];
    }
}
