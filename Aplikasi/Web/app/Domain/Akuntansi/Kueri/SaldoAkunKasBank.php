<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\JurnalDetail;
use App\Domain\Bersama\Nilai\Uang;

/**
 * Saldo per akun kas/bank (F-13a, tipe FE `BarisSaldoKasBank`): Σ debit − Σ kredit seluruh jurnal, dijumlah di SQL
 * per akun (DECIMAL, eksak) lewat indeks IdTenant+IdAkun+Tanggal. Pengguna berbatas outlet hanya menjumlah baris
 * jurnal di outlet aksesnya. Akun nonaktif tetap tampil bila saldonya bukan nol.
 */
final class SaldoAkunKasBank
{
    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return list<array{Uuid: string, Kode: string, Nama: string, Aktif: bool, Saldo: string}>
     */
    public function Ambil(?array $idOutletBoleh): array
    {
        $akun = Akun::query()->where('KasBank', true)->orderBy('Kode')->get(['Id', 'Uuid', 'Kode', 'Nama', 'Aktif']);

        if ($akun->isEmpty()) {
            return [];
        }

        $saldo = [];

        foreach (JurnalDetail::query()
            ->whereIn('IdAkun', $akun->pluck('Id')->all())
            ->when($idOutletBoleh !== null, fn ($k) => $k->whereIn('IdOutlet', $idOutletBoleh === [] ? [0] : $idOutletBoleh))
            ->groupBy('IdAkun')
            ->selectRaw('`IdAkun`, CAST(SUM(`Debit`) - SUM(`Kredit`) AS DECIMAL(20,2)) AS `Saldo`')
            ->toBase()
            ->get() as $baris) {
            $saldo[(int) $baris->IdAkun] = Uang::Dari((string) $baris->Saldo)->KeString();
        }

        $hasil = [];

        foreach ($akun as $satu) {
            $nilai = $saldo[$satu->Id] ?? '0.00';

            if (! $satu->Aktif && Uang::Dari($nilai)->BernilaiNol()) {
                continue;
            }

            $hasil[] = ['Uuid' => $satu->Uuid, 'Kode' => $satu->Kode, 'Nama' => $satu->Nama, 'Aktif' => $satu->Aktif, 'Saldo' => $nilai];
        }

        return $hasil;
    }
}
