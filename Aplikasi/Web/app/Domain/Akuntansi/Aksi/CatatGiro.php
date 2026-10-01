<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Aksi;

use App\Domain\Akuntansi\Data\DataGiroMasukan;
use App\Domain\Akuntansi\Enum\ArahGiro;
use App\Domain\Akuntansi\Enum\JenisSumberGiro;
use App\Domain\Akuntansi\Enum\StatusGiro;
use App\Domain\Akuntansi\Layanan\PenyediaAkunPeran;
use App\Domain\Akuntansi\Model\Giro;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;

/**
 * v3.42 (F-12): mencatat bilyet giro/cek mundur milik sebuah pelunasan piutang (Masuk) atau pembayaran hutang
 * (Keluar), di dalam transaksi pemanggil. `PeriksaIsian` dipanggil sebelum dokumen asal dibuat; `AmbilIdAkunPenampung`
 * memberi akun yang dipakai jurnal dokumen asal (Giro Mundur Diterima 1-1480 / Hutang Giro 2-1180, dibuat & dipetakan
 * bila tenant belum punya). Jatuh tempo tidak boleh sebelum tanggal terima dan paling lama satu tahun.
 */
final class CatatGiro
{
    public function __construct(private readonly PenyediaAkunPeran $penyedia) {}

    /**
     * @throws PelanggaranAturanBisnis NomorGiroWajib, NamaBankWajib, JatuhTempoTidakValid
     */
    public function PeriksaIsian(DataGiroMasukan $giro, CarbonImmutable $tanggalTerima): void
    {
        if (trim($giro->nomorGiro) === '' || mb_strlen(trim($giro->nomorGiro)) > 40) {
            throw new PelanggaranAturanBisnis('NomorGiroWajib', 'Isi nomor bilyet giro/cek (maks. 40 karakter).', 'Giro.NomorGiro');
        }

        if (trim($giro->namaBank) === '' || mb_strlen(trim($giro->namaBank)) > 80) {
            throw new PelanggaranAturanBisnis('NamaBankWajib', 'Isi nama bank penerbit giro (maks. 80 karakter).', 'Giro.NamaBank');
        }

        $jatuhTempo = $giro->tanggalJatuhTempo->toDateString();

        if ($jatuhTempo < $tanggalTerima->toDateString() || $jatuhTempo > $tanggalTerima->addYear()->toDateString()) {
            throw new PelanggaranAturanBisnis('JatuhTempoTidakValid', 'Tanggal efektif giro paling cepat tanggal terima dan paling lama satu tahun setelahnya.', 'Giro.TanggalJatuhTempo');
        }
    }

    public function AmbilIdAkunPenampung(ArahGiro $arah): int
    {
        return $arah === ArahGiro::Masuk
            ? $this->penyedia->Pastikan($arah->AmbilPeranPenampung(), '1-1480', 'Giro Mundur Diterima')
            : $this->penyedia->Pastikan($arah->AmbilPeranPenampung(), '2-1180', 'Hutang Giro');
    }

    public function Jalankan(
        ArahGiro $arah,
        JenisSumberGiro $jenisSumber,
        int $idSumber,
        string $nomorSumber,
        string $namaPihak,
        DataGiroMasukan $giro,
        CarbonImmutable $tanggalTerima,
        Uang $jumlah,
        int $idPengguna,
    ): Giro {
        $this->PeriksaIsian($giro, $tanggalTerima);

        return Giro::query()->create([
            'Arah' => $arah,
            'JenisSumber' => $jenisSumber,
            'IdSumber' => $idSumber,
            'NomorSumber' => $nomorSumber,
            'NamaPihak' => mb_substr($namaPihak, 0, 150),
            'NomorGiro' => mb_strtoupper(trim($giro->nomorGiro)),
            'NamaBank' => trim($giro->namaBank),
            'TanggalTerima' => $tanggalTerima->toDateString(),
            'TanggalJatuhTempo' => $giro->tanggalJatuhTempo->toDateString(),
            'Jumlah' => $jumlah->KeString(),
            'Status' => StatusGiro::Menunggu,
            'DibuatOleh' => $idPengguna,
        ]);
    }
}
