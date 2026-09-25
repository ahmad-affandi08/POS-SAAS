<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Aksi;

use App\Domain\Akuntansi\Aksi\BalikkanJurnal;
use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Karyawan\Enum\CaraPelunasanKasbon;
use App\Domain\Karyawan\Enum\StatusKaryawan;
use App\Domain\Karyawan\Enum\StatusKasbon;
use App\Domain\Karyawan\Model\Karyawan;
use App\Domain\Karyawan\Model\Kasbon;
use App\Domain\Karyawan\Model\PelunasanKasbon;
use App\Domain\Tenant\Kueri\ProfilTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * F-18 bagian 3 kasbon karyawan (EMP-06):
 * - **Catat** (J-18.1): karyawan aktif, jumlah > 0, akun kas/bank aktif, tanggal tidak di masa depan (zona tenant);
 *   jurnal Dr Piutang Karyawan (dimensi outlet utama karyawan), Cr kas/bank di transaksi yang sama. Audit `kasbon.catat`.
 * - **Pelunasan ke kas/bank**: jumlah ≤ sisa; Dr kas/bank, Cr Piutang Karyawan; sisa 0 = Lunas. Audit `kasbon.lunasi`.
 * - **Batalkan**: hanya kasbon tanpa pelunasan; jurnal dibalik hari ini, status Dibatalkan, alasan wajib. Audit
 *   `kasbon.batal`.
 * Potongan dari rekap gaji dicatat oleh rekap gaji lewat `PotongDariGaji` (jurnalnya ikut jurnal rekap gaji).
 */
final class KelolaKasbon
{
    public const PANJANG_ALASAN_MINIMAL = 5;

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly ProfilTenant $profil,
        private readonly DaftarAkunPilihan $akun,
        private readonly PostingJurnal $posting,
        private readonly BalikkanJurnal $balikkan,
        private readonly PencatatAudit $audit,
    ) {}

    public function Catat(string $uuidKaryawan, CarbonImmutable $tanggal, Uang $jumlah, string $uuidAkunKasBank, ?string $keterangan, int $idPengguna): Kasbon
    {
        $karyawan = Karyawan::query()->where('Uuid', $uuidKaryawan)->first();

        if ($karyawan === null || $karyawan->Status !== StatusKaryawan::Aktif) {
            throw new PelanggaranAturanBisnis('KaryawanTidakValid', 'Pilih karyawan yang aktif.', 'Karyawan');
        }

        self::PastikanJumlah($jumlah);
        $this->PastikanTanggal($tanggal);
        $akun = $this->CariAkunKasBank($uuidAkunKasBank);

        return DB::transaction(function () use ($karyawan, $tanggal, $jumlah, $akun, $keterangan, $idPengguna): Kasbon {
            $kasbon = Kasbon::query()->create([
                'IdKaryawan' => $karyawan->Id,
                'Tanggal' => $tanggal->toDateString(),
                'Jumlah' => $jumlah->KeString(),
                'Sisa' => $jumlah->KeString(),
                'IdAkunKasBank' => $akun,
                'Keterangan' => $keterangan,
                'Status' => StatusKasbon::Aktif,
                'DibuatOleh' => $idPengguna,
            ]);
            $hasil = $this->posting->Jalankan(new DataJurnal(
                jenisSumber: JenisSumberJurnal::Kasbon,
                idSumber: $kasbon->Id,
                uuidSumber: $kasbon->Uuid,
                nomorSumber: null,
                tanggal: $tanggal,
                keterangan: "Kasbon {$karyawan->Nama}",
                baris: [
                    DataBarisJurnal::Debit(PeranAkun::PiutangKaryawan, $jumlah, $karyawan->IdOutlet),
                    new DataBarisJurnal(peran: null, idAkun: $akun, idOutlet: $karyawan->IdOutlet, debit: Uang::Nol(), kredit: $jumlah),
                ],
                idPengguna: $idPengguna,
            ));
            $kasbon->IdJurnal = $hasil->idJurnal;
            $kasbon->save();
            $this->audit->Catat('kasbon.catat', $kasbon, nilaiBaru: ['Karyawan' => $karyawan->Nama, 'Jumlah' => $jumlah->KeString(), 'Tanggal' => $tanggal->toDateString()]);

            return $kasbon;
        });
    }

    public function Lunasi(Kasbon $kasbon, CarbonImmutable $tanggal, Uang $jumlah, string $uuidAkunKasBank, ?string $keterangan, int $idPengguna): PelunasanKasbon
    {
        self::PastikanJumlah($jumlah);
        $this->PastikanTanggal($tanggal);
        $akun = $this->CariAkunKasBank($uuidAkunKasBank);

        return DB::transaction(function () use ($kasbon, $tanggal, $jumlah, $akun, $keterangan, $idPengguna): PelunasanKasbon {
            $kasbon = $this->KunciAktif($kasbon, $jumlah);
            $karyawan = Karyawan::query()->whereKey($kasbon->IdKaryawan)->firstOrFail();
            $pelunasan = $this->SimpanPelunasan($kasbon, $tanggal, $jumlah, CaraPelunasanKasbon::KasBank, $akun, null, $keterangan, $idPengguna);
            $hasil = $this->posting->Jalankan(new DataJurnal(
                jenisSumber: JenisSumberJurnal::PelunasanKasbon,
                idSumber: $pelunasan->Id,
                uuidSumber: $kasbon->Uuid,
                nomorSumber: null,
                tanggal: $tanggal,
                keterangan: "Pelunasan kasbon {$karyawan->Nama}",
                baris: [
                    new DataBarisJurnal(peran: null, idAkun: $akun, idOutlet: $karyawan->IdOutlet, debit: $jumlah, kredit: Uang::Nol()),
                    DataBarisJurnal::Kredit(PeranAkun::PiutangKaryawan, $jumlah, $karyawan->IdOutlet),
                ],
                idPengguna: $idPengguna,
            ));
            $pelunasan->IdJurnal = $hasil->idJurnal;
            $pelunasan->save();
            $this->audit->Catat('kasbon.lunasi', $kasbon, nilaiBaru: ['Jumlah' => $jumlah->KeString(), 'Sisa' => $kasbon->Sisa]);

            return $pelunasan;
        });
    }

    /**
     * Potongan rekap gaji: dicatat tanpa jurnal sendiri (Cr Piutang Karyawan ada di jurnal rekap gaji). Dipanggil di
     * dalam transaksi rekap gaji.
     */
    public function PotongDariGaji(Kasbon $kasbon, CarbonImmutable $tanggal, Uang $jumlah, int $idRekapGaji, int $idPengguna): PelunasanKasbon
    {
        self::PastikanJumlah($jumlah);
        $kasbon = $this->KunciAktif($kasbon, $jumlah);

        return $this->SimpanPelunasan($kasbon, $tanggal, $jumlah, CaraPelunasanKasbon::PotongGaji, null, $idRekapGaji, 'Potong gaji', $idPengguna);
    }

    public function Batalkan(Kasbon $kasbon, string $alasan, int $idPengguna): Kasbon
    {
        $alasan = trim($alasan);

        if (mb_strlen($alasan) < self::PANJANG_ALASAN_MINIMAL) {
            throw new PelanggaranAturanBisnis('AlasanWajib', 'Tulis alasan membatalkan kasbon, minimal '.self::PANJANG_ALASAN_MINIMAL.' karakter.', 'Alasan');
        }

        return DB::transaction(function () use ($kasbon, $alasan, $idPengguna): Kasbon {
            $kasbon = Kasbon::query()->whereKey($kasbon->Id)->lockForUpdate()->firstOrFail();

            if ($kasbon->Status !== StatusKasbon::Aktif || PelunasanKasbon::query()->where('IdKasbon', $kasbon->Id)->exists()) {
                throw new PelanggaranAturanBisnis('KasbonTidakBisaDibatalkan', 'Hanya kasbon yang belum pernah dilunasi yang bisa dibatalkan.');
            }

            if ($kasbon->IdJurnal !== null) {
                $this->balikkan->Jalankan($kasbon->IdJurnal, $this->HariIni(), 'Pembatalan kasbon: '.$alasan, JenisSumberJurnal::Kasbon, $kasbon->Id, 'Pembatalan', $idPengguna);
            }

            $kasbon->fill(['Status' => StatusKasbon::Dibatalkan, 'Sisa' => '0.00', 'DibatalkanPada' => now(), 'AlasanBatal' => mb_substr($alasan, 0, 500)])->save();
            $this->audit->Catat('kasbon.batal', $kasbon, nilaiBaru: ['Alasan' => $alasan]);

            return $kasbon;
        });
    }

    private function KunciAktif(Kasbon $kasbon, Uang $jumlah): Kasbon
    {
        $kasbon = Kasbon::query()->whereKey($kasbon->Id)->lockForUpdate()->firstOrFail();

        if ($kasbon->Status !== StatusKasbon::Aktif) {
            throw new PelanggaranAturanBisnis('KasbonTidakAktif', 'Kasbon ini sudah lunas atau dibatalkan.');
        }

        if ($jumlah->Bandingkan(Uang::Dari($kasbon->Sisa)) > 0) {
            throw new PelanggaranAturanBisnis('MelebihiSisaKasbon', 'Jumlah melebihi sisa kasbon '.Uang::Dari($kasbon->Sisa)->FormatRupiah().'.', 'Jumlah');
        }

        return $kasbon;
    }

    private function SimpanPelunasan(Kasbon $kasbon, CarbonImmutable $tanggal, Uang $jumlah, CaraPelunasanKasbon $cara, ?int $akun, ?int $idRekapGaji, ?string $keterangan, int $idPengguna): PelunasanKasbon
    {
        $pelunasan = PelunasanKasbon::query()->create([
            'IdKasbon' => $kasbon->Id,
            'Tanggal' => $tanggal->toDateString(),
            'Jumlah' => $jumlah->KeString(),
            'Cara' => $cara,
            'IdAkunKasBank' => $akun,
            'IdRekapGaji' => $idRekapGaji,
            'Keterangan' => $keterangan,
            'DibuatOleh' => $idPengguna,
        ]);
        $sisa = Uang::Dari($kasbon->Sisa)->Kurangi($jumlah);
        $kasbon->fill(['Sisa' => $sisa->KeString(), 'Status' => $sisa->BernilaiNol() ? StatusKasbon::Lunas : StatusKasbon::Aktif])->save();

        return $pelunasan;
    }

    private static function PastikanJumlah(Uang $jumlah): void
    {
        if ($jumlah->Bandingkan(Uang::Nol()) <= 0) {
            throw new PelanggaranAturanBisnis('JumlahTidakValid', 'Jumlah harus lebih dari Rp 0.', 'Jumlah');
        }
    }

    private function PastikanTanggal(CarbonImmutable $tanggal): void
    {
        if ($tanggal->toDateString() > $this->HariIni()->toDateString()) {
            throw new PelanggaranAturanBisnis('TanggalDiMasaDepan', 'Tanggal tidak boleh setelah hari ini.', 'Tanggal');
        }
    }

    private function HariIni(): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::now($this->profil->Ambil($this->konteks->Wajib())['ZonaWaktu'])->toDateString());
    }

    private function CariAkunKasBank(string $uuid): int
    {
        return $this->akun->CariKasBankDariUuid($uuid)['Id'] ?? throw new PelanggaranAturanBisnis('AkunKasBankWajib', 'Pilih akun kas atau bank.', 'AkunKasBank');
    }
}
