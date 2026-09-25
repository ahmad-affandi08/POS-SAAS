<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Data\SaringLaporanKeuangan;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\JurnalDetail;
use App\Domain\Bersama\Nilai\Uang;

/**
 * Neraca saldo (F-13a, FIN-06, tipe FE `PropsNeracaSaldo`): per akun saldo awal (semua jurnal sebelum `dari`),
 * mutasi debit & kredit dalam rentang, dan saldo akhir, masing-masing di kolom debit/kredit. Satu kueri agregat per
 * akun di SQL (DECIMAL, eksak; indeks IdTenant+IdAkun+Tanggal); uang sebagai string desimal. Σ debit = Σ kredit untuk
 * mutasi maupun saldo karena setiap jurnal seimbang (`Seimbang` ditampilkan). Akun tanpa saldo & mutasi tidak tampil.
 */
final class NeracaSaldo
{
    private const KOLOM = ['SaldoAwalDebit', 'SaldoAwalKredit', 'Debit', 'Kredit', 'SaldoAkhirDebit', 'SaldoAkhirKredit'];

    /**
     * @return array{Baris: list<array<string, mixed>>, Total: array<string, string>, Seimbang: bool}
     */
    public function Ambil(SaringLaporanKeuangan $saring): array
    {
        $agregat = $saring->TerapkanOutlet(JurnalDetail::query()->where('Tanggal', '<=', $saring->sampai))
            ->groupBy('IdAkun')
            ->selectRaw(
                '`IdAkun`,'
                .' CAST(SUM(CASE WHEN `Tanggal` < ? THEN `Debit` - `Kredit` ELSE 0 END) AS DECIMAL(20,2)) AS `SaldoAwal`,'
                .' CAST(SUM(CASE WHEN `Tanggal` >= ? THEN `Debit` ELSE 0 END) AS DECIMAL(20,2)) AS `Debit`,'
                .' CAST(SUM(CASE WHEN `Tanggal` >= ? THEN `Kredit` ELSE 0 END) AS DECIMAL(20,2)) AS `Kredit`',
                [$saring->dari, $saring->dari, $saring->dari],
            )
            ->toBase()
            ->get()
            ->keyBy('IdAkun');

        $akun = Akun::query()->whereKey($agregat->keys()->map(fn ($id): int => (int) $id)->all())->orderBy('Kode')->get();
        $total = array_fill_keys(self::KOLOM, Uang::Nol());
        $baris = [];

        foreach ($akun as $satu) {
            $data = $agregat->get($satu->Id);
            $awal = Uang::Dari((string) ($data->SaldoAwal ?? '0'));
            $debit = Uang::Dari((string) ($data->Debit ?? '0'));
            $kredit = Uang::Dari((string) ($data->Kredit ?? '0'));
            $akhir = $awal->Tambah($debit)->Kurangi($kredit);

            if ($awal->BernilaiNol() && $debit->BernilaiNol() && $kredit->BernilaiNol()) {
                continue;
            }

            $nilai = [
                'SaldoAwalDebit' => self::SisiDebit($awal),
                'SaldoAwalKredit' => self::SisiKredit($awal),
                'Debit' => $debit,
                'Kredit' => $kredit,
                'SaldoAkhirDebit' => self::SisiDebit($akhir),
                'SaldoAkhirKredit' => self::SisiKredit($akhir),
            ];

            foreach ($nilai as $kolom => $uang) {
                $total[$kolom] = $total[$kolom]->Tambah($uang);
            }

            $baris[] = [
                'Uuid' => $satu->Uuid,
                'Kode' => $satu->Kode,
                'Nama' => $satu->Nama,
                'Jenis' => $satu->Jenis->value,
                'LabelJenis' => $satu->Jenis->AmbilLabel(),
                ...array_map(fn (Uang $u): string => $u->KeString(), $nilai),
            ];
        }

        return [
            'Baris' => $baris,
            'Total' => array_map(fn (Uang $u): string => $u->KeString(), $total),
            'Seimbang' => $total['Debit']->SamaDengan($total['Kredit'])
                && $total['SaldoAkhirDebit']->SamaDengan($total['SaldoAkhirKredit'])
                && $total['SaldoAwalDebit']->SamaDengan($total['SaldoAwalKredit']),
        ];
    }

    private static function SisiDebit(Uang $saldo): Uang
    {
        return $saldo->BernilaiNegatif() ? Uang::Nol() : $saldo;
    }

    private static function SisiKredit(Uang $saldo): Uang
    {
        return $saldo->BernilaiNegatif() ? Uang::Nol()->Kurangi($saldo) : Uang::Nol();
    }
}
