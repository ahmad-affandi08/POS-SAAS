<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\ArahGiro;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\StatusGiro;
use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Akuntansi\Layanan\PenjagaKunciPeriode;
use App\Domain\Akuntansi\Model\Giro;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * v3.42 (F-12): giro/cek mundur cair di rekening bank (izin `akuntansi.kelola`). Tanggal cair paling cepat tanggal
 * efektif giro, tidak di masa depan, dan periodenya terbuka. Jurnal: giro masuk Dr bank / Cr Giro Mundur Diterima;
 * giro keluar Dr Hutang Giro / Cr bank. Audit `giro.cair`.
 */
final class CairkanGiro
{
    public function __construct(
        private readonly DaftarAkunPilihan $akun,
        private readonly CatatGiro $catatGiro,
        private readonly PenjagaKunciPeriode $penjagaPeriode,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly PostingJurnal $postingJurnal,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @throws PelanggaranAturanBisnis AkunKasBankWajib, GiroSudahDiputuskan, BelumJatuhTempo, TanggalMasaDepan
     */
    public function Jalankan(Giro $giro, string $uuidAkun, CarbonImmutable $tanggal, int $idPengguna): Giro
    {
        $akun = $this->akun->CariKasBankDariUuid($uuidAkun);

        if ($akun === null) {
            throw new PelanggaranAturanBisnis('AkunKasBankWajib', 'Pilih rekening bank tempat giro dicairkan.', 'UuidAkun');
        }

        $hariIni = $this->tanggalBisnis->Hitung(null);

        if ($tanggal->toDateString() > $hariIni->toDateString()) {
            throw new PelanggaranAturanBisnis('TanggalMasaDepan', 'Tanggal cair tidak boleh setelah hari ini ('.$hariIni->format('d/m/Y').').', 'Tanggal');
        }

        $this->penjagaPeriode->PastikanTerbuka($tanggal);

        return DB::transaction(function () use ($giro, $akun, $tanggal, $idPengguna): Giro {
            $terkunci = Giro::query()->whereKey($giro->Id)->lockForUpdate()->firstOrFail();

            if ($terkunci->Status !== StatusGiro::Menunggu) {
                throw new PelanggaranAturanBisnis('GiroSudahDiputuskan', "Giro {$terkunci->NomorGiro} sudah {$terkunci->Status->AmbilLabel()}.");
            }

            if ($tanggal->toDateString() < $terkunci->TanggalJatuhTempo->toDateString()) {
                throw new PelanggaranAturanBisnis('BelumJatuhTempo', 'Giro baru bisa dicairkan mulai tanggal efektifnya ('.$terkunci->TanggalJatuhTempo->format('d/m/Y').').', 'Tanggal');
            }

            $jumlah = Uang::Dari($terkunci->Jumlah);
            $idPenampung = $this->catatGiro->AmbilIdAkunPenampung($terkunci->Arah);
            $memo = "Giro {$terkunci->NomorGiro} ({$terkunci->NomorSumber})";
            $baris = $terkunci->Arah === ArahGiro::Masuk
                ? [new DataBarisJurnal(null, $akun['Id'], null, $jumlah, Uang::Nol(), $memo), new DataBarisJurnal(null, $idPenampung, null, Uang::Nol(), $jumlah, $memo)]
                : [new DataBarisJurnal(null, $idPenampung, null, $jumlah, Uang::Nol(), $memo), new DataBarisJurnal(null, $akun['Id'], null, Uang::Nol(), $jumlah, $memo)];
            $jurnal = $this->postingJurnal->Jalankan(new DataJurnal(
                jenisSumber: JenisSumberJurnal::Giro,
                idSumber: $terkunci->Id,
                uuidSumber: $terkunci->Uuid,
                nomorSumber: $terkunci->NomorGiro,
                tanggal: $tanggal,
                keterangan: mb_substr("Pencairan giro {$terkunci->NomorGiro} {$terkunci->NamaBank} — {$terkunci->NamaPihak}", 0, 255),
                baris: $baris,
                idPengguna: $idPengguna,
            ));

            $terkunci->UbahStatus(StatusGiro::Cair);
            $terkunci->forceFill([
                'IdAkunCair' => $akun['Id'],
                'TanggalCair' => $tanggal->toDateString(),
                'IdJurnalCair' => $jurnal->idJurnal,
                'DiputuskanOleh' => $idPengguna,
                'DiputuskanPada' => now(),
            ])->save();
            $this->audit->Catat('giro.cair', $terkunci, ['Status' => StatusGiro::Menunggu->value], [
                'Status' => StatusGiro::Cair->value,
                'NomorGiro' => $terkunci->NomorGiro,
                'Jumlah' => $terkunci->Jumlah,
                'NomorJurnal' => $jurnal->nomor,
            ], idPengguna: $idPengguna);

            return $terkunci;
        }, 3);
    }
}
