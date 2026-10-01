<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Layanan;

use App\Domain\Akuntansi\Model\JurnalDetail;
use App\Domain\Akuntansi\Model\MutasiBank;
use App\Domain\Bersama\Nilai\Uang;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Kandidat baris jurnal untuk satu mutasi rekening koran (FIN-09): akun yang sama, sisi yang sesuai (uang masuk
 * rekening = debit akun bank, uang keluar = kredit), nominal sama persis, tanggal dalam ±[TOLERANSI_HARI] hari, dan
 * belum dicocokkan dengan mutasi lain. Urut dari tanggal terdekat.
 */
final class PencariKandidatMutasi
{
    public const TOLERANSI_HARI = 5;

    /**
     * @return Collection<int, JurnalDetail>
     */
    public function Cari(MutasiBank $mutasi, int $batas = 10): Collection
    {
        $masuk = ! Uang::Dari($mutasi->Masuk)->BernilaiNol();
        $tanggal = CarbonImmutable::parse($mutasi->Tanggal->toDateString());

        return JurnalDetail::query()
            ->where('IdAkun', $mutasi->IdAkun)
            ->where($masuk ? 'Debit' : 'Kredit', $masuk ? $mutasi->Masuk : $mutasi->Keluar)
            ->whereBetween('Tanggal', [$tanggal->subDays(self::TOLERANSI_HARI)->toDateString(), $tanggal->addDays(self::TOLERANSI_HARI)->toDateString()])
            ->whereNotIn('Id', MutasiBank::query()->whereNotNull('IdJurnalDetail')->select('IdJurnalDetail'))
            ->with('Jurnal:Id,Uuid,Nomor,Keterangan,Tanggal')
            ->orderByRaw('ABS(DATEDIFF(`Tanggal`, ?))', [$tanggal->toDateString()])
            ->orderBy('Id')
            ->limit($batas)
            ->get();
    }
}
