<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Data\DataTransaksiKasBank;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\JenisTransaksiKasBank;
use App\Domain\Akuntansi\Layanan\PenjagaKunciPeriode;
use App\Domain\Akuntansi\Layanan\PenyimpanLampiranKasBank;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\TransaksiKasBank;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Enum\JenisDokumenBernomor;
use App\Domain\Bersama\Dokumen\Layanan\PenomorDokumen;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * F-13a transaksi kas & bank (FIN-03): simpan dokumen `KB/{YYYY}/{MM}/{SEQ4}` dan posting jurnalnya di transaksi DB
 * yang sama (aturan #10): Dr akun tujuan, Cr akun sumber sebesar jumlah, dimensi outlet opsional. Aturan akun per
 * `JenisTransaksiKasBank` (kas/bank = akun bertanda `KasBank`); akun harus aktif. Periode terkunci ditolak
 * (`PeriodeTerkunci`). Dokumen append-only; koreksi lewat `BalikkanTransaksiKasBank`. LogAudit `kas-bank.simpan`.
 */
final class SimpanTransaksiKasBank
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenjagaKunciPeriode $penjagaPeriode,
        private readonly PenomorDokumen $penomor,
        private readonly PostingJurnal $posting,
        private readonly PenyimpanLampiranKasBank $lampiran,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis AkunTidakDikenal, AkunKasBankWajib, AkunLawanTidakValid, AkunTransferSama,
     *                                 JumlahTidakValid, PeriodeTerkunci
     */
    public function Jalankan(DataTransaksiKasBank $data): TransaksiKasBank
    {
        $idTenant = $this->konteks->Wajib();
        $sumber = self::CariAkun($data->uuidAkunSumber, 'UuidAkunSumber');
        $tujuan = self::CariAkun($data->uuidAkunTujuan, 'UuidAkunTujuan');
        self::PeriksaAkun($data->jenis, $sumber, $tujuan);

        if ($data->jumlah->Bandingkan(Uang::Nol()) <= 0) {
            throw new PelanggaranAturanBisnis('JumlahTidakValid', 'Jumlah harus lebih dari Rp 0.', 'Jumlah');
        }

        $this->penjagaPeriode->PastikanTerbuka($data->tanggal);
        $berkas = $data->lampiran === null ? null : $this->lampiran->Simpan($idTenant, $data->lampiran);

        try {
            return DB::transaction(function () use ($data, $sumber, $tujuan, $berkas): TransaksiKasBank {
                $periode = $data->tanggal->format('Y-m');
                $nomor = $this->penomor->AmbilNomorBerikutnya(JenisDokumenBernomor::TransaksiKasBank, $periode);
                $keterangan = mb_substr(trim($data->keterangan), 0, 255);

                $transaksi = TransaksiKasBank::query()->create([
                    'Nomor' => $nomor,
                    'Jenis' => $data->jenis,
                    'Tanggal' => $data->tanggal->toDateString(),
                    'IdOutlet' => $data->idOutlet,
                    'IdAkunSumber' => $sumber->Id,
                    'IdAkunTujuan' => $tujuan->Id,
                    'Jumlah' => $data->jumlah->KeString(),
                    'Keterangan' => $keterangan,
                    'PathLampiran' => $berkas['Path'] ?? null,
                    'NamaLampiran' => $berkas['NamaAsli'] ?? null,
                    'MimeLampiran' => $berkas['Mime'] ?? null,
                    'UkuranLampiran' => $berkas['Ukuran'] ?? null,
                    'DibuatOleh' => $data->idPengguna,
                ]);

                $jurnal = $this->posting->Jalankan(new DataJurnal(
                    jenisSumber: JenisSumberJurnal::TransaksiKasBank,
                    idSumber: $transaksi->Id,
                    uuidSumber: $transaksi->Uuid,
                    nomorSumber: $nomor,
                    tanggal: $data->tanggal,
                    keterangan: "{$data->jenis->AmbilLabel()} {$nomor}: {$keterangan}",
                    baris: [
                        new DataBarisJurnal(null, $tujuan->Id, $data->idOutlet, $data->jumlah, Uang::Nol(), $keterangan),
                        new DataBarisJurnal(null, $sumber->Id, $data->idOutlet, Uang::Nol(), $data->jumlah, $keterangan),
                    ],
                    idPengguna: $data->idPengguna,
                ));

                $this->audit->Catat('kas-bank.simpan', $transaksi, nilaiBaru: [
                    'Nomor' => $nomor,
                    'Jenis' => $data->jenis->value,
                    'Tanggal' => $data->tanggal->toDateString(),
                    'IdOutlet' => $data->idOutlet,
                    'KodeAkunSumber' => $sumber->Kode,
                    'KodeAkunTujuan' => $tujuan->Kode,
                    'Jumlah' => $data->jumlah->KeString(),
                    'NomorJurnal' => $jurnal->nomor,
                ], idPengguna: $data->idPengguna);

                return $transaksi;
            }, 3);
        } catch (Throwable $galat) {
            if ($berkas !== null) {
                $this->lampiran->Hapus($berkas['Path']);
            }

            throw $galat;
        }
    }

    /**
     * @throws PelanggaranAturanBisnis AkunKasBankWajib, AkunLawanTidakValid, AkunTransferSama
     */
    public static function PeriksaAkun(JenisTransaksiKasBank $jenis, Akun $sumber, Akun $tujuan): void
    {
        foreach (['UuidAkunSumber' => [$sumber, $jenis->CekSumberKasBank()], 'UuidAkunTujuan' => [$tujuan, $jenis->CekTujuanKasBank()]] as $bidang => [$akun, $wajibKasBank]) {
            if ($wajibKasBank && ! $akun->KasBank) {
                throw new PelanggaranAturanBisnis('AkunKasBankWajib', "Akun {$akun->AmbilLabel()} bukan akun kas/bank. Pilih akun kas/bank.", $bidang);
            }

            if (! $wajibKasBank && ($akun->KasBank || ! in_array($akun->Jenis, $jenis->AmbilTipeAkunLawan(), true))) {
                $boleh = implode(', ', array_map(fn ($t): string => mb_strtolower($t->AmbilLabel()), $jenis->AmbilTipeAkunLawan()));

                throw new PelanggaranAturanBisnis('AkunLawanTidakValid', "{$jenis->AmbilLabel()} memakai akun {$boleh} selain kas/bank; {$akun->AmbilLabel()} tidak bisa dipakai.", $bidang);
            }
        }

        if ($sumber->Id === $tujuan->Id) {
            throw new PelanggaranAturanBisnis('AkunTransferSama', 'Akun asal dan tujuan harus berbeda.', 'UuidAkunTujuan');
        }
    }

    private static function CariAkun(string $uuid, string $bidang): Akun
    {
        $akun = Akun::query()->where('Uuid', $uuid)->where('Aktif', true)->first();

        if (! $akun instanceof Akun) {
            throw new PelanggaranAturanBisnis('AkunTidakDikenal', 'Akun tidak ditemukan atau nonaktif.', $bidang);
        }

        return $akun;
    }
}
