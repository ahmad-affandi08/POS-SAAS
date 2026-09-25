<?php

declare(strict_types=1);

namespace App\Domain\Karyawan\Aksi;

use App\Domain\Akuntansi\Aksi\PostingJurnal;
use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Enum\TipeAkun;
use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Karyawan\Enum\StatusKaryawan;
use App\Domain\Karyawan\Enum\StatusRekapGaji;
use App\Domain\Karyawan\Kueri\DaftarKasbon;
use App\Domain\Karyawan\Kueri\LaporanKomisi;
use App\Domain\Karyawan\Model\Karyawan;
use App\Domain\Karyawan\Model\RekapGaji;
use App\Domain\Karyawan\Model\RekapGajiBaris;
use App\Domain\Tenant\Kueri\ProfilTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * F-18 bagian 3 rekap gaji bulanan (EMP-06):
 * - **Buat** draf periode `YYYY-MM` (tidak setelah bulan berjalan, satu per periode): baris untuk karyawan aktif yang
 *   punya gaji pokok atau komisi di periode itu. Komisi = komisi bersih (komisi − dibatalkan) tanggal bisnis periode.
 *   Potongan kasbon awal = min(sisa kasbon aktif, gaji kotor). Audit `rekap-gaji.buat`.
 * - **Ubah baris** (draf): tambahan (lembur/tunjangan), potongan kasbon (≤ sisa kasbon aktif), potongan lain, catatan;
 *   gaji bersih tidak boleh minus. Audit `rekap-gaji.ubah`.
 * - **Hapus** draf. Audit `rekap-gaji.hapus`.
 * - **Bayar**: satu jurnal seimbang per outlet utama karyawan: Dr akun beban (bawaan 6-1000 Beban Gaji & Komisi) Σ
 *   kotor, Cr Piutang Karyawan Σ potongan kasbon, Cr Pendapatan Lain Σ potongan lain, Cr kas/bank Σ bersih. Potongan
 *   kasbon dicatat sebagai pelunasan kasbon terlama dulu. Status Dibayar (append-only). Audit `rekap-gaji.bayar`.
 * Komisi yang dibatalkan setelah gaji dibayar (retur bulan berikutnya) tidak dipotong otomatis.
 */
final class KelolaRekapGaji
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly ProfilTenant $profil,
        private readonly LaporanKomisi $komisi,
        private readonly DaftarKasbon $kasbon,
        private readonly KelolaKasbon $kelolaKasbon,
        private readonly DaftarAkunPilihan $akun,
        private readonly PostingJurnal $posting,
        private readonly PencatatAudit $audit,
    ) {}

    public function Buat(string $periode, int $idPengguna): RekapGaji
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode) !== 1) {
            throw new PelanggaranAturanBisnis('PeriodeTidakValid', 'Periode harus berformat tahun-bulan, misal 2026-09.', 'Periode');
        }

        if ($periode > $this->HariIni()->format('Y-m')) {
            throw new PelanggaranAturanBisnis('PeriodeBelumMulai', 'Rekap gaji hanya untuk bulan berjalan atau sebelumnya.', 'Periode');
        }

        $awal = CarbonImmutable::createFromFormat('!Y-m', $periode) ?: throw new PelanggaranAturanBisnis('PeriodeTidakValid', 'Periode tidak valid.', 'Periode');

        return DB::transaction(function () use ($periode, $awal, $idPengguna): RekapGaji {
            if (RekapGaji::query()->where('Periode', $periode)->lockForUpdate()->exists()) {
                throw new PelanggaranAturanBisnis('RekapSudahAda', "Rekap gaji {$periode} sudah dibuat.", 'Periode');
            }

            $komisi = $this->komisi->AmbilBersihPerKaryawan($awal->toDateString(), $awal->endOfMonth()->toDateString());
            $karyawan = Karyawan::query()
                ->where(fn ($k) => $k->where('Status', StatusKaryawan::Aktif->value)->orWhereIn('Id', array_keys($komisi)))
                ->orderBy('Nama')
                ->get();
            $sisaKasbon = $this->HitungSisaKasbon(array_values(array_map('intval', $karyawan->pluck('Id')->all())));
            $rekap = RekapGaji::query()->create(['Periode' => $periode, 'Status' => StatusRekapGaji::Draf, 'DibuatOleh' => $idPengguna]);

            foreach ($karyawan as $k) {
                $pokok = Uang::Dari($k->GajiPokok ?? '0');
                $nilaiKomisi = Uang::Dari($komisi[$k->Id] ?? '0');

                if ($pokok->BernilaiNol() && $nilaiKomisi->BernilaiNol()) {
                    continue;
                }

                $kotor = $pokok->Tambah($nilaiKomisi);
                $sisa = $sisaKasbon[$k->Id] ?? Uang::Nol();
                $potong = $sisa->Bandingkan($kotor) > 0 ? $kotor : $sisa;
                RekapGajiBaris::query()->create([
                    'IdRekapGaji' => $rekap->Id,
                    'IdKaryawan' => $k->Id,
                    'GajiPokok' => $pokok->KeString(),
                    'Komisi' => $nilaiKomisi->KeString(),
                    'Tambahan' => '0.00',
                    'PotonganKasbon' => $potong->KeString(),
                    'PotonganLain' => '0.00',
                    'Bersih' => $kotor->Kurangi($potong)->KeString(),
                ]);
            }

            $this->HitungTotal($rekap);
            $this->audit->Catat('rekap-gaji.buat', $rekap, nilaiBaru: ['Periode' => $periode]);

            return $rekap;
        });
    }

    public function UbahBaris(RekapGaji $rekap, string $uuidKaryawan, Uang $tambahan, Uang $potonganKasbon, Uang $potonganLain, ?string $catatan): RekapGajiBaris
    {
        foreach (['Tambahan' => $tambahan, 'PotonganKasbon' => $potonganKasbon, 'PotonganLain' => $potonganLain] as $bidang => $nilai) {
            if ($nilai->BernilaiNegatif()) {
                throw new PelanggaranAturanBisnis('JumlahTidakValid', 'Jumlah tidak boleh minus.', $bidang);
            }
        }

        return DB::transaction(function () use ($rekap, $uuidKaryawan, $tambahan, $potonganKasbon, $potonganLain, $catatan): RekapGajiBaris {
            $rekap = $this->KunciDraf($rekap);
            $idKaryawan = Karyawan::query()->where('Uuid', $uuidKaryawan)->value('Id');
            $baris = $idKaryawan === null ? null : RekapGajiBaris::query()->where('IdRekapGaji', $rekap->Id)->where('IdKaryawan', $idKaryawan)->first();

            if ($baris === null) {
                throw new PelanggaranAturanBisnis('BarisTidakDikenal', 'Karyawan ini tidak ada di rekap gaji.');
            }

            $sisa = $this->HitungSisaKasbon([$baris->IdKaryawan])[$baris->IdKaryawan] ?? Uang::Nol();

            if ($potonganKasbon->Bandingkan($sisa) > 0) {
                throw new PelanggaranAturanBisnis('MelebihiSisaKasbon', 'Potongan kasbon melebihi sisa kasbon '.$sisa->FormatRupiah().'.', 'PotonganKasbon');
            }

            $lama = $baris->only(['Tambahan', 'PotonganKasbon', 'PotonganLain', 'Catatan']);
            $baris->fill([
                'Tambahan' => $tambahan->KeString(),
                'PotonganKasbon' => $potonganKasbon->KeString(),
                'PotonganLain' => $potonganLain->KeString(),
                'Catatan' => $catatan,
            ]);
            $bersih = $baris->HitungKotor()->Kurangi($baris->HitungPotongan());

            if ($bersih->BernilaiNegatif()) {
                throw new PelanggaranAturanBisnis('GajiBersihMinus', 'Potongan melebihi gaji kotor. Kurangi potongan.', 'PotonganLain');
            }

            $baris->Bersih = $bersih->KeString();
            $baris->save();
            $this->HitungTotal($rekap);
            $this->audit->Catat('rekap-gaji.ubah', $rekap, nilaiLama: $lama, nilaiBaru: $baris->only(['Tambahan', 'PotonganKasbon', 'PotonganLain', 'Catatan']));

            return $baris;
        });
    }

    public function Hapus(RekapGaji $rekap): void
    {
        DB::transaction(function () use ($rekap): void {
            $rekap = $this->KunciDraf($rekap);
            RekapGajiBaris::query()->where('IdRekapGaji', $rekap->Id)->delete();
            $this->audit->Catat('rekap-gaji.hapus', $rekap, nilaiLama: ['Periode' => $rekap->Periode, 'TotalBersih' => $rekap->TotalBersih]);
            $rekap->delete();
        });
    }

    public function Bayar(RekapGaji $rekap, CarbonImmutable $tanggal, string $uuidAkunKasBank, string $uuidAkunBeban, int $idPengguna): RekapGaji
    {
        if ($tanggal->toDateString() > $this->HariIni()->toDateString()) {
            throw new PelanggaranAturanBisnis('TanggalDiMasaDepan', 'Tanggal bayar tidak boleh setelah hari ini.', 'Tanggal');
        }

        $akunKas = $this->akun->CariKasBankDariUuid($uuidAkunKasBank)['Id'] ?? throw new PelanggaranAturanBisnis('AkunKasBankWajib', 'Pilih akun kas atau bank.', 'AkunKasBank');
        $akunBeban = $this->akun->CariDariUuid($uuidAkunBeban, [TipeAkun::Beban])['Id'] ?? throw new PelanggaranAturanBisnis('AkunBebanWajib', 'Pilih akun beban gaji.', 'AkunBeban');

        return DB::transaction(function () use ($rekap, $tanggal, $akunKas, $akunBeban, $idPengguna): RekapGaji {
            $rekap = $this->KunciDraf($rekap);
            $baris = RekapGajiBaris::query()->where('IdRekapGaji', $rekap->Id)->get();

            if ($baris->isEmpty() || Uang::Dari($rekap->TotalKotor)->BernilaiNol()) {
                throw new PelanggaranAturanBisnis('RekapKosong', 'Rekap gaji ini belum berisi gaji untuk dibayar.');
            }

            $outlet = Karyawan::query()->whereKey($baris->pluck('IdKaryawan')->all())->pluck('IdOutlet', 'Id');
            /** @var array<int, array{Kotor: Uang, Kasbon: Uang, Lain: Uang, Bersih: Uang}> $perOutlet kunci 0 = tanpa outlet */
            $perOutlet = [];

            foreach ($baris as $b) {
                $kunci = (int) ($outlet[$b->IdKaryawan] ?? 0);
                $nilai = $perOutlet[$kunci] ?? ['Kotor' => Uang::Nol(), 'Kasbon' => Uang::Nol(), 'Lain' => Uang::Nol(), 'Bersih' => Uang::Nol()];
                $perOutlet[$kunci] = [
                    'Kotor' => $nilai['Kotor']->Tambah($b->HitungKotor()),
                    'Kasbon' => $nilai['Kasbon']->Tambah(Uang::Dari($b->PotonganKasbon)),
                    'Lain' => $nilai['Lain']->Tambah(Uang::Dari($b->PotonganLain)),
                    'Bersih' => $nilai['Bersih']->Tambah(Uang::Dari($b->Bersih)),
                ];
            }

            $barisJurnal = [];

            foreach ($perOutlet as $kunci => $n) {
                $idOutlet = $kunci === 0 ? null : $kunci;
                $barisJurnal[] = new DataBarisJurnal(peran: null, idAkun: $akunBeban, idOutlet: $idOutlet, debit: $n['Kotor'], kredit: Uang::Nol(), memo: "Gaji {$rekap->Periode}");
                $barisJurnal[] = DataBarisJurnal::Kredit(PeranAkun::PiutangKaryawan, $n['Kasbon'], $idOutlet, 'Potongan kasbon');
                $barisJurnal[] = DataBarisJurnal::Kredit(PeranAkun::PendapatanLain, $n['Lain'], $idOutlet, 'Potongan lain gaji');
                $barisJurnal[] = new DataBarisJurnal(peran: null, idAkun: $akunKas, idOutlet: $idOutlet, debit: Uang::Nol(), kredit: $n['Bersih'], memo: 'Gaji dibayar');
            }

            $hasil = $this->posting->Jalankan(new DataJurnal(
                jenisSumber: JenisSumberJurnal::RekapGaji,
                idSumber: $rekap->Id,
                uuidSumber: $rekap->Uuid,
                nomorSumber: $rekap->Periode,
                tanggal: $tanggal,
                keterangan: "Rekap gaji {$rekap->Periode}",
                baris: $barisJurnal,
                idPengguna: $idPengguna,
            ));

            $this->PotongKasbon($rekap, array_values($baris->all()), $tanggal, $idPengguna);
            $rekap->fill([
                'Status' => StatusRekapGaji::Dibayar,
                'TanggalBayar' => $tanggal->toDateString(),
                'IdAkunKasBank' => $akunKas,
                'IdAkunBeban' => $akunBeban,
                'IdJurnal' => $hasil->idJurnal,
                'DibayarOleh' => $idPengguna,
                'DibayarPada' => now(),
            ])->save();
            $this->audit->Catat('rekap-gaji.bayar', $rekap, nilaiBaru: ['Periode' => $rekap->Periode, 'TotalBersih' => $rekap->TotalBersih, 'Nomor' => $hasil->nomor]);

            return $rekap;
        });
    }

    /**
     * @param  list<RekapGajiBaris>  $baris
     */
    private function PotongKasbon(RekapGaji $rekap, array $baris, CarbonImmutable $tanggal, int $idPengguna): void
    {
        $kasbon = $this->kasbon->AmbilAktifPerKaryawan(array_map(fn (RekapGajiBaris $b): int => $b->IdKaryawan, $baris));

        foreach ($baris as $b) {
            $sisaPotong = Uang::Dari($b->PotonganKasbon);

            foreach ($kasbon[$b->IdKaryawan] ?? [] as $k) {
                if ($sisaPotong->BernilaiNol()) {
                    break;
                }

                $sisaKasbon = Uang::Dari($k->Sisa);
                $potong = $sisaPotong->Bandingkan($sisaKasbon) > 0 ? $sisaKasbon : $sisaPotong;
                $this->kelolaKasbon->PotongDariGaji($k, $tanggal, $potong, $rekap->Id, $idPengguna);
                $sisaPotong = $sisaPotong->Kurangi($potong);
            }

            if (! $sisaPotong->BernilaiNol()) {
                throw new PelanggaranAturanBisnis('MelebihiSisaKasbon', 'Potongan kasbon melebihi sisa kasbon karyawan. Muat ulang rekap lalu sesuaikan potongan.');
            }
        }
    }

    private function KunciDraf(RekapGaji $rekap): RekapGaji
    {
        $rekap = RekapGaji::query()->whereKey($rekap->Id)->lockForUpdate()->firstOrFail();

        if ($rekap->Status !== StatusRekapGaji::Draf) {
            throw new PelanggaranAturanBisnis('RekapSudahDibayar', 'Rekap gaji yang sudah dibayar tidak bisa diubah.');
        }

        return $rekap;
    }

    private function HitungTotal(RekapGaji $rekap): void
    {
        $kotor = Uang::Nol();
        $potongan = Uang::Nol();
        $bersih = Uang::Nol();

        foreach (RekapGajiBaris::query()->where('IdRekapGaji', $rekap->Id)->get() as $b) {
            $kotor = $kotor->Tambah($b->HitungKotor());
            $potongan = $potongan->Tambah($b->HitungPotongan());
            $bersih = $bersih->Tambah(Uang::Dari($b->Bersih));
        }

        $rekap->fill(['TotalKotor' => $kotor->KeString(), 'TotalPotongan' => $potongan->KeString(), 'TotalBersih' => $bersih->KeString()])->save();
    }

    /**
     * @param  list<int>  $idKaryawan
     * @return array<int, Uang>
     */
    private function HitungSisaKasbon(array $idKaryawan): array
    {
        $hasil = [];

        foreach ($this->kasbon->AmbilAktifPerKaryawan($idKaryawan) as $id => $daftar) {
            $hasil[$id] = array_reduce($daftar, fn (Uang $t, $k): Uang => $t->Tambah(Uang::Dari($k->Sisa)), Uang::Nol());
        }

        return $hasil;
    }

    private function HariIni(): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::now($this->profil->Ambil($this->konteks->Wajib())['ZonaWaktu'])->toDateString());
    }
}
