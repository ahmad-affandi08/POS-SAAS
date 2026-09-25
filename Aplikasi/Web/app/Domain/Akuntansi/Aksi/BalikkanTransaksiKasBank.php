<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Model\Jurnal;
use App\Domain\Akuntansi\Model\TransaksiKasBank;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Dokumen\Enum\JenisDokumenBernomor;
use App\Domain\Bersama\Dokumen\Layanan\PenomorDokumen;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * F-13a koreksi transaksi kas & bank (aturan #8): dokumen pembalik `KB/…` baru (`IdTransaksiDibalik`) dengan jurnal
 * pembalik (debit ↔ kredit, `IdJurnalDibalik`) di transaksi DB yang sama. Dokumen asal tidak diubah. Satu dokumen hanya
 * bisa dibalik sekali; dokumen pembalik tidak bisa dibalik lagi. Tanggal pembalik tidak boleh sebelum tanggal asal dan
 * harus di periode terbuka. LogAudit `kas-bank.balik`.
 */
final class BalikkanTransaksiKasBank
{
    public function __construct(
        private readonly PenomorDokumen $penomor,
        private readonly PostingJurnal $posting,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis PembalikTidakBisaDibalik, TransaksiSudahDibalik, TanggalPembalikTidakValid,
     *                                 PeriodeTerkunci, JurnalTidakDikenal
     */
    public function Jalankan(TransaksiKasBank $asal, CarbonImmutable $tanggal, string $alasan, ?int $idPengguna): TransaksiKasBank
    {
        return DB::transaction(function () use ($asal, $tanggal, $alasan, $idPengguna): TransaksiKasBank {
            $asal = TransaksiKasBank::query()->whereKey($asal->Id)->lockForUpdate()->firstOrFail();

            if ($asal->IdTransaksiDibalik !== null) {
                throw new PelanggaranAturanBisnis('PembalikTidakBisaDibalik', "{$asal->Nomor} adalah dokumen pembalik dan tidak bisa dibalik lagi.");
            }

            $pembalikAda = TransaksiKasBank::query()->where('IdTransaksiDibalik', $asal->Id)->value('Nomor');

            if (is_string($pembalikAda)) {
                throw new PelanggaranAturanBisnis('TransaksiSudahDibalik', "{$asal->Nomor} sudah dibalik oleh {$pembalikAda}.");
            }

            if ($tanggal->lt($asal->Tanggal->toImmutable()->startOfDay())) {
                throw new PelanggaranAturanBisnis('TanggalPembalikTidakValid', 'Tanggal pembalik tidak boleh sebelum tanggal transaksi asal ('.$asal->Tanggal->format('d/m/Y').').', 'Tanggal');
            }

            $jurnalAsal = Jurnal::query()
                ->where('JenisSumber', JenisSumberJurnal::TransaksiKasBank->value)
                ->where('IdSumber', $asal->Id)
                ->where('KunciSumber', 'Utama')
                ->first(['Id', 'Nomor']);

            if (! $jurnalAsal instanceof Jurnal) {
                throw new PelanggaranAturanBisnis('JurnalTidakDikenal', "Jurnal {$asal->Nomor} tidak ditemukan.");
            }

            $nomor = $this->penomor->AmbilNomorBerikutnya(JenisDokumenBernomor::TransaksiKasBank, $tanggal->format('Y-m'));
            $keterangan = mb_substr('Pembalik '.$asal->Nomor.': '.trim($alasan), 0, 255);
            $jumlah = Uang::Dari($asal->Jumlah);

            $pembalik = TransaksiKasBank::query()->create([
                'Nomor' => $nomor,
                'Jenis' => $asal->Jenis,
                'Tanggal' => $tanggal->toDateString(),
                'IdOutlet' => $asal->IdOutlet,
                'IdAkunSumber' => $asal->IdAkunSumber,
                'IdAkunTujuan' => $asal->IdAkunTujuan,
                'Jumlah' => $jumlah->KeString(),
                'Keterangan' => $keterangan,
                'IdTransaksiDibalik' => $asal->Id,
                'DibuatOleh' => $idPengguna,
            ]);

            // Cermin jurnal asal: Dr akun sumber, Cr akun tujuan.
            $jurnal = $this->posting->Jalankan(new DataJurnal(
                jenisSumber: JenisSumberJurnal::TransaksiKasBank,
                idSumber: $pembalik->Id,
                uuidSumber: $pembalik->Uuid,
                nomorSumber: $nomor,
                tanggal: $tanggal,
                keterangan: $keterangan,
                baris: [
                    new DataBarisJurnal(null, $asal->IdAkunSumber, $asal->IdOutlet, $jumlah, Uang::Nol(), $keterangan),
                    new DataBarisJurnal(null, $asal->IdAkunTujuan, $asal->IdOutlet, Uang::Nol(), $jumlah, $keterangan),
                ],
                idPengguna: $idPengguna,
                idJurnalDibalik: $jurnalAsal->Id,
            ));

            $this->audit->Catat('kas-bank.balik', $pembalik, nilaiBaru: [
                'Nomor' => $nomor,
                'NomorDibalik' => $asal->Nomor,
                'Tanggal' => $tanggal->toDateString(),
                'Jumlah' => $jumlah->KeString(),
                'Alasan' => trim($alasan),
                'NomorJurnal' => $jurnal->nomor,
            ], idPengguna: $idPengguna);

            return $pembalik;
        }, 3);
    }
}
