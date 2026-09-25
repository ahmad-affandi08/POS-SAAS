<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Data\HasilPostingJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Enum\TipeAkun;
use App\Domain\Akuntansi\Kueri\StatusTutupTahun;
use App\Domain\Akuntansi\Layanan\PenjagaKunciPeriode;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\JurnalDetail;
use App\Domain\Akuntansi\Model\KunciPeriode;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Tenant\Kueri\ProfilTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * F-15 tutup tahun (J-15.1): jurnal penutup bertanggal 31 Desember yang menolkan saldo setahun setiap akun
 * pendapatan, HPP, dan beban per outlet, selisihnya (laba/rugi bersih) ke Laba Ditahan per outlet, sehingga neraca per
 * outlet tetap seimbang. Syarat: tahun sudah berakhir, 12 bulannya terkunci, belum pernah ditutup, dan ada saldo laba
 * rugi. Setelah ditutup, bulan-bulan tahun itu tidak bisa dibuka kuncinya (koreksi dicatat di tahun berikutnya).
 * Idempoten per tahun lewat `PostingJurnal` (sumber `TutupTahun`, IdSumber = tahun). Audit `akuntansi.tahun.tutup`.
 */
final class TutupTahunAkuntansi
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly ProfilTenant $profil,
        private readonly StatusTutupTahun $status,
        private readonly PostingJurnal $posting,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(int $tahun, int $idPengguna): HasilPostingJurnal
    {
        $idTenant = $this->konteks->Wajib();
        $tahunIni = CarbonImmutable::now($this->profil->Ambil($idTenant)['ZonaWaktu'])->year;

        if ($tahun < 2000 || $tahun >= $tahunIni) {
            throw new PelanggaranAturanBisnis('TahunBelumBerakhir', "Tahun {$tahun} belum berakhir. Tahun buku hanya bisa ditutup setelah 31 Desember.", 'Tahun');
        }

        return DB::transaction(function () use ($tahun, $idPengguna): HasilPostingJurnal {
            $terkunci = KunciPeriode::query()->where('Periode', 'like', $tahun.'-%')->lockForUpdate()->pluck('Periode')->all();
            $belum = array_values(array_diff(array_map(fn (int $b): string => sprintf('%d-%02d', $tahun, $b), range(1, 12)), $terkunci));

            if ($belum !== []) {
                throw new PelanggaranAturanBisnis(
                    'PeriodeBelumTerkunci',
                    'Kunci dulu semua bulan tahun '.$tahun.'. Belum dikunci: '.implode(', ', array_map(fn (string $p): string => PenjagaKunciPeriode::FormatPeriode($p), $belum)).'.',
                    'Tahun',
                    detail: ['Periode' => $belum],
                );
            }

            if ($this->status->CekDitutup($tahun)) {
                throw new PelanggaranAturanBisnis('TahunSudahDitutup', "Tahun buku {$tahun} sudah ditutup.", 'Tahun');
            }

            [$baris, $laba] = $this->SusunBaris($tahun);

            if ($baris === []) {
                throw new PelanggaranAturanBisnis('TidakAdaSaldoLabaRugi', "Tidak ada saldo pendapatan, HPP, atau beban di tahun {$tahun}. Tahun ini tidak perlu ditutup.", 'Tahun');
            }

            $hasil = $this->posting->Jalankan(new DataJurnal(
                jenisSumber: JenisSumberJurnal::TutupTahun,
                idSumber: $tahun,
                uuidSumber: null,
                nomorSumber: (string) $tahun,
                tanggal: CarbonImmutable::create($tahun, 12, 31) ?? throw new PelanggaranAturanBisnis('TahunTidakValid', 'Tahun tidak valid.', 'Tahun'),
                keterangan: "Jurnal penutup tahun {$tahun}: laba rugi ke Laba Ditahan",
                baris: $baris,
                idPengguna: $idPengguna,
            ));
            $this->audit->Catat('akuntansi.tahun.tutup', null, nilaiBaru: ['Tahun' => $tahun, 'Nomor' => $hasil->nomor, 'LabaBersih' => $laba->KeString()], idPengguna: $idPengguna);

            return $hasil;
        });
    }

    /**
     * Baris penutup per (akun laba rugi, outlet) dan Laba Ditahan per outlet.
     *
     * @return array{0: list<DataBarisJurnal>, 1: Uang}
     */
    private function SusunBaris(int $tahun): array
    {
        $idAkun = Akun::query()->whereIn('Jenis', [TipeAkun::Pendapatan->value, TipeAkun::Hpp->value, TipeAkun::Beban->value])->pluck('Id')->all();

        if ($idAkun === []) {
            return [[], Uang::Nol()];
        }

        $saldo = JurnalDetail::query()
            ->whereIn('IdAkun', $idAkun)
            ->whereBetween('Tanggal', ["{$tahun}-01-01", "{$tahun}-12-31"])
            ->groupBy('IdAkun', 'IdOutlet')
            ->orderBy('IdOutlet')
            ->orderBy('IdAkun')
            ->selectRaw('`IdAkun`, `IdOutlet`, CAST(SUM(`Debit` - `Kredit`) AS DECIMAL(20,2)) AS `Saldo`')
            ->toBase()
            ->get();

        $baris = [];
        /** @var array<int, Uang> $labaPerOutlet kunci 0 = tanpa outlet */
        $labaPerOutlet = [];
        $laba = Uang::Nol();

        foreach ($saldo as $s) {
            $nilai = Uang::Dari((string) $s->Saldo);

            if ($nilai->BernilaiNol()) {
                continue;
            }

            $idOutlet = $s->IdOutlet === null ? null : (int) $s->IdOutlet;
            // Saldo debit (biaya) ditutup dengan kredit, saldo kredit (pendapatan) dengan debit.
            $baris[] = new DataBarisJurnal(
                peran: null,
                idAkun: (int) $s->IdAkun,
                idOutlet: $idOutlet,
                debit: $nilai->BernilaiNegatif() ? Uang::Nol()->Kurangi($nilai) : Uang::Nol(),
                kredit: $nilai->BernilaiNegatif() ? Uang::Nol() : $nilai,
                memo: "Penutupan {$tahun}",
            );
            // Laba = kredit − debit = −saldo.
            $labaPerOutlet[$idOutlet ?? 0] = ($labaPerOutlet[$idOutlet ?? 0] ?? Uang::Nol())->Kurangi($nilai);
            $laba = $laba->Kurangi($nilai);
        }

        foreach ($labaPerOutlet as $kunci => $nilai) {
            $idOutlet = $kunci === 0 ? null : $kunci;

            if (! $nilai->BernilaiNol()) {
                $baris[] = $nilai->BernilaiNegatif()
                    ? DataBarisJurnal::Debit(PeranAkun::LabaDitahan, Uang::Nol()->Kurangi($nilai), $idOutlet, "Rugi {$tahun}")
                    : DataBarisJurnal::Kredit(PeranAkun::LabaDitahan, $nilai, $idOutlet, "Laba {$tahun}");
            }
        }

        return [$baris, $laba];
    }
}
