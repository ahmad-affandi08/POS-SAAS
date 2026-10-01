<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Enum\StatusAsetTetap;
use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Akuntansi\Layanan\PenyediaAkunAsetTetap;
use App\Domain\Akuntansi\Model\AsetTetap;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Melepas aset tetap (FIN-10): dijual atau dibuang/rusak. Penyusutan disusul dulu sampai bulan pelepasan, lalu jurnal
 * pelepasan: Dr kas/bank (nilai jual, bila ada) + Dr Akumulasi Penyusutan (seluruh akumulasi) / Cr Aset Tetap (harga
 * perolehan); selisihnya **laba** (Cr Pendapatan Lain) atau **rugi** pelepasan (Dr Beban Penyusutan, memo "Rugi
 * pelepasan"). Tanggal tidak boleh sebelum perolehan atau setelah hari ini. Audit `aset-tetap.lepas`.
 */
final class LepasAsetTetap
{
    public function __construct(
        private readonly SusutkanAsetTetap $susutkan,
        private readonly PenyediaAkunAsetTetap $bantuan,
        private readonly DaftarAkunPilihan $akun,
        private readonly PostingJurnal $posting,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(AsetTetap $aset, CarbonImmutable $tanggal, Uang $nilaiJual, ?string $uuidAkunKasBank, ?string $catatan, int $idPengguna): AsetTetap
    {
        if ($aset->Status !== StatusAsetTetap::Aktif) {
            throw new PelanggaranAturanBisnis('AsetTidakAktif', 'Aset ini sudah dilepas atau dibatalkan.');
        }

        if ($tanggal->toDateString() > $this->bantuan->HariIni()->toDateString() || $tanggal->toDateString() < $aset->TanggalPerolehan->toDateString()) {
            throw new PelanggaranAturanBisnis('TanggalTidakValid', 'Tanggal pelepasan antara tanggal perolehan dan hari ini.', 'Tanggal');
        }

        if ($nilaiJual->BernilaiNegatif()) {
            throw new PelanggaranAturanBisnis('NilaiTidakValid', 'Nilai jual tidak boleh negatif.', 'NilaiJual');
        }

        $idAkun = null;

        if (! $nilaiJual->BernilaiNol()) {
            $idAkun = $uuidAkunKasBank === null ? null : ($this->akun->CariKasBankDariUuid($uuidAkunKasBank)['Id'] ?? null);

            if ($idAkun === null) {
                throw new PelanggaranAturanBisnis('AkunKasBankWajib', 'Pilih akun kas atau bank penerima hasil penjualan.', 'AkunKasBank');
            }
        }

        // Penyusutan sampai bulan pelepasan (bulan pelepasan ikut disusutkan penuh).
        $this->susutkan->Jalankan($tanggal->format('Y-m'), $idPengguna, $aset->Id);

        return DB::transaction(function () use ($aset, $tanggal, $nilaiJual, $idAkun, $catatan, $idPengguna): AsetTetap {
            $aset = AsetTetap::query()->whereKey($aset->Id)->lockForUpdate()->firstOrFail();

            if ($aset->Status !== StatusAsetTetap::Aktif) {
                throw new PelanggaranAturanBisnis('AsetTidakAktif', 'Aset ini sudah dilepas atau dibatalkan.');
            }

            $harga = Uang::Dari($aset->HargaPerolehan);
            $akumulasi = PenyediaAkunAsetTetap::HitungAkumulasi($aset);
            $selisih = $nilaiJual->Tambah($akumulasi)->Kurangi($harga);
            $baris = [];

            if ($idAkun !== null) {
                $baris[] = new DataBarisJurnal(peran: null, idAkun: $idAkun, idOutlet: $aset->IdOutlet, debit: $nilaiJual, kredit: Uang::Nol());
            }

            if (! $akumulasi->BernilaiNol()) {
                $baris[] = DataBarisJurnal::Debit(PeranAkun::AkumulasiPenyusutan, $akumulasi, $aset->IdOutlet, $aset->Nomor);
            }

            if ($selisih->BernilaiNegatif()) {
                $baris[] = DataBarisJurnal::Debit(PeranAkun::BebanPenyusutan, Uang::Nol()->Kurangi($selisih), $aset->IdOutlet, 'Rugi pelepasan '.$aset->Nomor);
            }

            $baris[] = DataBarisJurnal::Kredit(PeranAkun::AsetTetap, $harga, $aset->IdOutlet, $aset->Nomor);

            if (! $selisih->BernilaiNol() && ! $selisih->BernilaiNegatif()) {
                $baris[] = DataBarisJurnal::Kredit(PeranAkun::PendapatanLain, $selisih, $aset->IdOutlet, 'Laba pelepasan '.$aset->Nomor);
            }

            $hasil = $this->posting->Jalankan(new DataJurnal(
                jenisSumber: JenisSumberJurnal::AsetTetap,
                idSumber: $aset->Id,
                uuidSumber: $aset->Uuid,
                nomorSumber: $aset->Nomor,
                tanggal: $tanggal,
                keterangan: ($nilaiJual->BernilaiNol() ? 'Penghapusan aset tetap ' : 'Penjualan aset tetap ').$aset->Nama,
                baris: $baris,
                idPengguna: $idPengguna,
                kunciSumber: 'Pelepasan',
            ));
            $aset->fill([
                'Status' => StatusAsetTetap::Dilepas,
                'TanggalPelepasan' => $tanggal->toDateString(),
                'NilaiPelepasan' => $nilaiJual->KeString(),
                'IdAkunPelepasan' => $idAkun,
                'IdJurnalPelepasan' => $hasil->idJurnal,
                'Catatan' => $catatan === null || trim($catatan) === '' ? $aset->Catatan : mb_substr(trim($catatan), 0, 500),
            ])->save();
            $this->audit->Catat('aset-tetap.lepas', $aset, nilaiBaru: [
                'Tanggal' => $tanggal->toDateString(),
                'NilaiJual' => $nilaiJual->KeString(),
                'Akumulasi' => $akumulasi->KeString(),
                'LabaRugi' => $selisih->KeString(),
            ], idPengguna: $idPengguna);

            return $aset;
        });
    }
}
