<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tenant\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Tenant\Kueri\PemilikTenant;
use App\Domain\Pengelola\Tenant\Surel\LanggananDiaktifkanKembali;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\Paket;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

/**
 * Aktifkan kembali tenant yang ditangguhkan (P-07, BR-P07.5) oleh Keuangan atau Super Admin, dengan keputusan tertulis
 * (alasan wajib). Status yang dipulihkan adalah status sebelum penangguhan manual; trial yang habis selama ditangguhkan
 * turun ke paket Gratis (BR-00.3). Penangguhan tanpa status asal (dari penagihan, P-08) dipulihkan ke `Aktif`.
 */
final class AktifkanKembaliTenant
{
    public function __construct(
        private readonly PencatatAuditPengelola $audit,
        private readonly PemilikTenant $pemilik,
    ) {}

    /** Status tujuan bila langganan ini diaktifkan kembali sekarang. */
    public static function TentukanTujuan(Langganan $langganan): StatusLangganan
    {
        $asal = $langganan->StatusSebelumDitangguhkan ?? StatusLangganan::Aktif;

        if ($asal === StatusLangganan::Trial && ($langganan->TrialBerakhirPada === null || ! $langganan->TrialBerakhirPada->isFuture())) {
            return StatusLangganan::Gratis;
        }

        return $asal;
    }

    public function Jalankan(PenggunaPengelola $pelaku, Tenant $tenant, string $alasan): Langganan
    {
        $langganan = DB::transaction(function () use ($pelaku, $tenant, $alasan): Langganan {
            $langganan = Langganan::query()->where('IdTenant', $tenant->Id)->lockForUpdate()->first()
                ?? throw new PelanggaranAturanBisnis('LanggananTidakAda', 'Tenant ini belum punya langganan.');

            if ($langganan->Status !== StatusLangganan::Ditangguhkan) {
                throw new PelanggaranAturanBisnis('BR-P07.5', 'Hanya tenant yang ditangguhkan yang bisa diaktifkan kembali.');
            }

            $tujuan = self::TentukanTujuan($langganan);
            $nilaiLama = ['Status' => $langganan->Status->value, 'IdPaket' => $langganan->IdPaket];
            $ubah = ['Status' => $tujuan, 'StatusSebelumDitangguhkan' => null];

            if ($tujuan === StatusLangganan::Gratis && $langganan->StatusSebelumDitangguhkan === StatusLangganan::Trial) {
                $ubah['IdPaket'] = (Paket::query()->where('Kode', (string) config('tenant.KodePaketGratis'))->first()
                    ?? throw new RuntimeException('Paket Gratis (config tenant.KodePaketGratis) tidak ditemukan.'))->Id;
            }

            $langganan->update($ubah);

            $this->audit->Catat(
                'tenant.aktifkan',
                $langganan,
                nilaiLama: $nilaiLama,
                nilaiBaru: ['Status' => $tujuan->value, 'IdPaket' => $langganan->IdPaket],
                alasan: $alasan,
                idPelaku: $pelaku->Id,
                idTenant: $tenant->Id,
            );

            return $langganan;
        });

        foreach ($this->pemilik->Ambil($tenant->Id) as $pemilik) {
            try {
                Mail::to($pemilik['Email'])->send(new LanggananDiaktifkanKembali($pemilik['Nama'], $tenant->Nama, $langganan->Status->AmbilLabel()));
            } catch (Throwable $galat) {
                Log::warning('Email pengaktifan kembali tenant gagal dikirim.', ['IdTenant' => $tenant->Id, 'Galat' => $galat->getMessage()]);
            }
        }

        return $langganan;
    }
}
