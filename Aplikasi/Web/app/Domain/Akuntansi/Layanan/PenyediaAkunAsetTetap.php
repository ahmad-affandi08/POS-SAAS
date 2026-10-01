<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Layanan;

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Model\AsetTetap;
use App\Domain\Akuntansi\Model\PenyusutanAset;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Tenant\Kueri\ProfilTenant;
use Carbon\CarbonImmutable;

/**
 * Bantuan bersama aksi aset tetap (FIN-10): memastikan tiga peran akunnya terpetakan (tenant yang menerapkan template
 * sebelum v3.38), hari ini menurut zona tenant, dan akumulasi penyusutan sebuah aset.
 */
final class PenyediaAkunAsetTetap
{
    public function __construct(
        private readonly PenyediaAkunPeran $penyedia,
        private readonly KonteksTenant $konteks,
        private readonly ProfilTenant $profil,
    ) {}

    public function PastikanAkun(): void
    {
        $this->penyedia->Pastikan(PeranAkun::AsetTetap, '1-2000', 'Aset Tetap');
        $this->penyedia->Pastikan(PeranAkun::AkumulasiPenyusutan, '1-2900', 'Akumulasi Penyusutan');
        $this->penyedia->Pastikan(PeranAkun::BebanPenyusutan, '6-5000', 'Beban Penyusutan');
    }

    public function HariIni(): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::now($this->profil->Ambil($this->konteks->Wajib())['ZonaWaktu'])->toDateString());
    }

    public static function HitungAkumulasi(AsetTetap $aset): Uang
    {
        $disusutkan = (string) PenyusutanAset::query()->where('IdAsetTetap', $aset->Id)->sum('Jumlah');

        return Uang::Dari($aset->AkumulasiAwal)->Tambah(Uang::Dari($disusutkan === '' ? '0' : $disusutkan));
    }
}
