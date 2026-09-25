<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Layanan;

use App\Domain\Akuntansi\Data\DataBarisJurnal;
use App\Domain\Akuntansi\Data\DataJurnal;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Kasir\Model\Shift;
use Carbon\CarbonImmutable;

/**
 * Jurnal selisih kas tutup shift (PRD §11.3, F-11), dimensi outlet shift, sumber `TutupShift` (tautan ke detail
 * shift):
 * - Kurang (selisih < 0) J-11.1: Dr Beban Selisih Kas / Cr Kas Outlet.
 * - Lebih (selisih > 0) J-11.2: Dr Kas Outlet / Cr Pendapatan Lain.
 * Selisih nol = tanpa jurnal (null).
 */
final class PenyusunJurnalSelisihKas
{
    public function Susun(Shift $shift, Uang $selisih, CarbonImmutable $tanggal, int $idPengguna): ?DataJurnal
    {
        if ($selisih->BernilaiNol()) {
            return null;
        }

        $kurang = $selisih->BernilaiNegatif();
        $nilai = $kurang ? Uang::Nol()->Kurangi($selisih) : $selisih;
        $memo = $shift->AlasanSelisih;

        $baris = $kurang
            ? [
                DataBarisJurnal::Debit(PeranAkun::BebanSelisihKas, $nilai, $shift->IdOutlet, $memo),
                DataBarisJurnal::Kredit(PeranAkun::KasOutlet, $nilai, $shift->IdOutlet),
            ]
            : [
                DataBarisJurnal::Debit(PeranAkun::KasOutlet, $nilai, $shift->IdOutlet),
                DataBarisJurnal::Kredit(PeranAkun::PendapatanLain, $nilai, $shift->IdOutlet, $memo),
            ];

        return new DataJurnal(
            jenisSumber: JenisSumberJurnal::TutupShift,
            idSumber: $shift->Id,
            uuidSumber: $shift->Uuid,
            nomorSumber: null,
            tanggal: $tanggal,
            keterangan: mb_substr(($kurang ? 'Selisih kas kurang' : 'Selisih kas lebih')." tutup shift ({$shift->Uuid})", 0, 255),
            baris: $baris,
            idPengguna: $idPengguna,
        );
    }
}
