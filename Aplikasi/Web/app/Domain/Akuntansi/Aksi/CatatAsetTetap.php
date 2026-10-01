<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Data\DataAsetTetap;
use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\KelompokAsetTetap;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Enum\StatusAsetTetap;
use App\Domain\Akuntansi\Enum\SumberDanaAsetTetap;
use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Akuntansi\Layanan\PenyediaAkunAsetTetap;
use App\Domain\Akuntansi\Model\AsetTetap;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Enum\JenisDokumenBernomor;
use App\Domain\Bersama\Dokumen\Layanan\PenomorDokumen;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use Illuminate\Support\Facades\DB;

/**
 * Mencatat aset tetap (FIN-10) beserta jurnal perolehannya di transaksi yang sama:
 * - **Dibeli sekarang** (`KasBank`): Dr Aset Tetap / Cr akun kas/bank pada tanggal perolehan; penyusutan mulai bulan
 *   perolehan.
 * - **Saldo awal** (harta yang sudah dimiliki sebelum memakai aplikasi): Dr Aset Tetap (harga perolehan) / Cr Akumulasi
 *   Penyusutan (akumulasi yang sudah ada) / Cr Ekuitas Saldo Awal (sisanya) bertanggal hari ini; penyusutan mulai
 *   bulan berjalan untuk sisa masa manfaat.
 * Ditolak: nama kosong, harga ≤ 0, nilai sisa negatif atau ≥ harga, masa manfaat di luar 1–600 bulan (tanah selalu
 * 0 = tidak disusutkan), tanggal perolehan di masa depan, akun kas/bank tidak dipilih, akumulasi awal melebihi nilai
 * yang bisa disusutkan. Audit `aset-tetap.catat`.
 */
final class CatatAsetTetap
{
    public const UMUR_MAKSIMAL_BULAN = 600;

    public function __construct(
        private readonly PenyediaAkunAsetTetap $bantuan,
        private readonly DaftarAkunPilihan $akun,
        private readonly PostingJurnal $posting,
        private readonly PenomorDokumen $penomor,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(DataAsetTetap $data): AsetTetap
    {
        $nama = trim($data->nama);
        $umur = $data->kelompok === KelompokAsetTetap::Tanah ? 0 : $data->umurBulan;
        $saldoAwal = $data->sumberDana === SumberDanaAsetTetap::SaldoAwal;
        $akumulasiAwal = $saldoAwal ? $data->akumulasiAwal : Uang::Nol();
        $hariIni = $this->bantuan->HariIni();

        if ($nama === '') {
            throw new PelanggaranAturanBisnis('NamaWajib', 'Isi nama aset.', 'Nama');
        }

        if ($data->hargaPerolehan->Bandingkan(Uang::Nol()) <= 0) {
            throw new PelanggaranAturanBisnis('HargaTidakValid', 'Harga perolehan harus lebih dari Rp 0.', 'HargaPerolehan');
        }

        if ($data->nilaiSisa->BernilaiNegatif() || $data->nilaiSisa->Bandingkan($data->hargaPerolehan) >= 0) {
            throw new PelanggaranAturanBisnis('NilaiSisaTidakValid', 'Nilai sisa harus Rp 0 atau lebih dan kurang dari harga perolehan.', 'NilaiSisa');
        }

        if ($data->kelompok !== KelompokAsetTetap::Tanah && ($umur < 1 || $umur > self::UMUR_MAKSIMAL_BULAN)) {
            throw new PelanggaranAturanBisnis('UmurTidakValid', 'Masa manfaat 1 sampai 600 bulan.', 'UmurBulan');
        }

        if ($data->tanggalPerolehan->toDateString() > $hariIni->toDateString()) {
            throw new PelanggaranAturanBisnis('TanggalDiMasaDepan', 'Tanggal perolehan tidak boleh setelah hari ini.', 'TanggalPerolehan');
        }

        if ($akumulasiAwal->BernilaiNegatif() || $akumulasiAwal->Bandingkan($data->hargaPerolehan->Kurangi($data->nilaiSisa)) > 0) {
            throw new PelanggaranAturanBisnis('AkumulasiAwalTidakValid', 'Akumulasi penyusutan awal tidak boleh negatif atau melebihi harga perolehan dikurangi nilai sisa.', 'AkumulasiAwal');
        }

        $idAkunKasBank = null;

        if (! $saldoAwal) {
            $idAkunKasBank = $data->uuidAkunKasBank === null ? null : ($this->akun->CariKasBankDariUuid($data->uuidAkunKasBank)['Id'] ?? null);

            if ($idAkunKasBank === null) {
                throw new PelanggaranAturanBisnis('AkunKasBankWajib', 'Pilih akun kas atau bank yang dipakai membayar.', 'AkunKasBank');
            }
        }

        $periodePerolehan = $data->tanggalPerolehan->format('Y-m');
        $periodeMulai = $saldoAwal ? max($periodePerolehan, $hariIni->format('Y-m')) : $periodePerolehan;
        $tanggalJurnal = $saldoAwal ? $hariIni : $data->tanggalPerolehan;
        $this->bantuan->PastikanAkun();

        return DB::transaction(function () use ($data, $nama, $umur, $akumulasiAwal, $idAkunKasBank, $saldoAwal, $periodeMulai, $tanggalJurnal): AsetTetap {
            $aset = AsetTetap::query()->create([
                'Nomor' => $this->penomor->AmbilNomorBerikutnya(JenisDokumenBernomor::AsetTetap, $tanggalJurnal->format('Y-m')),
                'Nama' => mb_substr($nama, 0, 150),
                'Kelompok' => $data->kelompok,
                'IdOutlet' => $data->idOutlet,
                'TanggalPerolehan' => $data->tanggalPerolehan->toDateString(),
                'HargaPerolehan' => $data->hargaPerolehan->KeString(),
                'NilaiSisa' => $data->nilaiSisa->KeString(),
                'UmurBulan' => $umur,
                'AkumulasiAwal' => $akumulasiAwal->KeString(),
                'PeriodeMulai' => $periodeMulai,
                'SumberDana' => $data->sumberDana,
                'IdAkunSumber' => $idAkunKasBank,
                'Status' => StatusAsetTetap::Aktif,
                'Catatan' => $data->catatan === null || trim($data->catatan) === '' ? null : mb_substr(trim($data->catatan), 0, 500),
                'DibuatOleh' => $data->idPengguna,
            ]);

            $harga = $data->hargaPerolehan;
            $baris = [DataBarisJurnal::Debit(PeranAkun::AsetTetap, $harga, $data->idOutlet, $aset->Nomor)];

            if ($saldoAwal) {
                if (! $akumulasiAwal->BernilaiNol()) {
                    $baris[] = DataBarisJurnal::Kredit(PeranAkun::AkumulasiPenyusutan, $akumulasiAwal, $data->idOutlet, 'Akumulasi awal');
                }

                $baris[] = DataBarisJurnal::Kredit(PeranAkun::EkuitasSaldoAwal, $harga->Kurangi($akumulasiAwal), $data->idOutlet);
            } else {
                $baris[] = new DataBarisJurnal(peran: null, idAkun: $idAkunKasBank, idOutlet: $data->idOutlet, debit: Uang::Nol(), kredit: $harga);
            }

            $hasil = $this->posting->Jalankan(new DataJurnal(
                jenisSumber: JenisSumberJurnal::AsetTetap,
                idSumber: $aset->Id,
                uuidSumber: $aset->Uuid,
                nomorSumber: $aset->Nomor,
                tanggal: $tanggalJurnal,
                keterangan: ($saldoAwal ? 'Saldo awal aset tetap ' : 'Perolehan aset tetap ').$aset->Nama,
                baris: $baris,
                idPengguna: $data->idPengguna,
            ));
            $aset->IdJurnal = $hasil->idJurnal;
            $aset->save();
            $this->audit->Catat('aset-tetap.catat', $aset, nilaiBaru: [
                'Nomor' => $aset->Nomor,
                'Nama' => $aset->Nama,
                'HargaPerolehan' => $harga->KeString(),
                'UmurBulan' => $umur,
                'SumberDana' => $data->sumberDana->value,
            ], idPengguna: $data->idPengguna);

            return $aset;
        });
    }
}
