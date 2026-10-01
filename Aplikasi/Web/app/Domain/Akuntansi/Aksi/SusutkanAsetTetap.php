<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Enum\StatusAsetTetap;
use App\Domain\Akuntansi\Layanan\PenghitungPenyusutan;
use App\Domain\Akuntansi\Layanan\PenyediaAkunAsetTetap;
use App\Domain\Akuntansi\Model\AsetTetap;
use App\Domain\Akuntansi\Model\PenyusutanAset;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use Illuminate\Support\Facades\DB;

/**
 * Menjurnal penyusutan bulanan aset tetap aktif (FIN-10) sampai `sampaiPeriode` (`YYYY-MM`, paling jauh bulan
 * berjalan). Setiap (aset, bulan) yang belum dijurnal = satu `PenyusutanAset` + jurnal Dr Beban Penyusutan / Cr
 * Akumulasi Penyusutan bertanggal akhir bulan, dalam satu transaksi DB; bulan yang tertinggal ikut disusul. Idempoten
 * lewat kunci unik (aset, periode). Periode yang sudah dikunci dibukukan `PostingJurnal` di periode terbuka berikutnya.
 * Dipanggil jadwal harian (bulan lalu), tombol "Susutkan sekarang", dan sebelum pelepasan aset.
 */
final class SusutkanAsetTetap
{
    public function __construct(
        private readonly PostingJurnal $posting,
        private readonly PenyediaAkunAsetTetap $bantuan,
    ) {}

    /**
     * @return array{Dicatat: int, Total: string}
     */
    public function Jalankan(string $sampaiPeriode, ?int $idPengguna = null, ?int $idAsetTetap = null): array
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $sampaiPeriode) !== 1) {
            throw new PelanggaranAturanBisnis('PeriodeTidakValid', 'Periode harus berformat TTTT-BB.', 'Periode');
        }

        if ($sampaiPeriode > $this->bantuan->HariIni()->format('Y-m')) {
            throw new PelanggaranAturanBisnis('PeriodeDiMasaDepan', 'Penyusutan paling jauh sampai bulan berjalan.', 'Periode');
        }

        $daftar = AsetTetap::query()
            ->where('Status', StatusAsetTetap::Aktif->value)
            ->where('UmurBulan', '>', 0)
            ->where('PeriodeMulai', '<=', $sampaiPeriode)
            ->when($idAsetTetap !== null, fn ($kueri) => $kueri->whereKey($idAsetTetap))
            ->orderBy('Id')
            ->get();

        if ($daftar->isEmpty()) {
            return ['Dicatat' => 0, 'Total' => '0.00'];
        }

        $this->bantuan->PastikanAkun();
        $dicatat = 0;
        $total = Uang::Nol();

        foreach ($daftar as $aset) {
            $sudah = PenyusutanAset::query()->where('IdAsetTetap', $aset->Id)->pluck('Periode')->flip()->all();
            $jadwal = PenghitungPenyusutan::HitungJadwal(
                Uang::Dari($aset->HargaPerolehan),
                Uang::Dari($aset->NilaiSisa),
                Uang::Dari($aset->AkumulasiAwal),
                $aset->UmurBulan,
                $aset->TanggalPerolehan->format('Y-m'),
                $aset->PeriodeMulai,
            );

            foreach ($jadwal as $bulan) {
                if ($bulan['Periode'] > $sampaiPeriode) {
                    break;
                }

                if (isset($sudah[$bulan['Periode']]) || $bulan['Jumlah']->BernilaiNol()) {
                    continue;
                }

                if ($this->CatatSatu($aset->Id, $bulan['Periode'], $bulan['Jumlah'], $idPengguna)) {
                    $dicatat++;
                    $total = $total->Tambah($bulan['Jumlah']);
                }
            }
        }

        return ['Dicatat' => $dicatat, 'Total' => $total->KeString()];
    }

    private function CatatSatu(int $idAset, string $periode, Uang $jumlah, ?int $idPengguna): bool
    {
        return DB::transaction(function () use ($idAset, $periode, $jumlah, $idPengguna): bool {
            $aset = AsetTetap::query()->whereKey($idAset)->lockForUpdate()->firstOrFail();

            if ($aset->Status !== StatusAsetTetap::Aktif || PenyusutanAset::query()->where('IdAsetTetap', $idAset)->where('Periode', $periode)->exists()) {
                return false;
            }

            $baris = PenyusutanAset::query()->create(['IdAsetTetap' => $aset->Id, 'Periode' => $periode, 'Jumlah' => $jumlah->KeString()]);
            $hasil = $this->posting->Jalankan(new DataJurnal(
                jenisSumber: JenisSumberJurnal::PenyusutanAset,
                idSumber: $baris->Id,
                uuidSumber: $aset->Uuid,
                nomorSumber: $aset->Nomor,
                tanggal: PenghitungPenyusutan::KeTanggal($periode)->endOfMonth()->startOfDay(),
                keterangan: "Penyusutan {$aset->Nama} {$periode}",
                baris: [
                    DataBarisJurnal::Debit(PeranAkun::BebanPenyusutan, $jumlah, $aset->IdOutlet, $aset->Nomor),
                    DataBarisJurnal::Kredit(PeranAkun::AkumulasiPenyusutan, $jumlah, $aset->IdOutlet, $aset->Nomor),
                ],
                idPengguna: $idPengguna,
            ));
            $baris->IdJurnal = $hasil->idJurnal;
            $baris->save();

            return true;
        });
    }
}
