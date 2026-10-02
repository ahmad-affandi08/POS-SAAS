<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Kasir\Enum\StatusShift;
use App\Domain\Kasir\Model\Shift;
use Carbon\CarbonInterface;

/**
 * Modul Salesman bagian 3 (§9.7, kanvas): setoran kas per outlet pada satu tanggal bisnis, dari data tutup shift F-11
 * yang sudah ada. Hanya shift `Tertutup` yang dijumlah (kas awal, kas seharusnya, kas dihitung, selisih); shift lain
 * (masih terbuka, dibuka ulang) hanya dihitung jumlahnya supaya halaman bisa menandai setoran yang belum lengkap.
 */
final class SetoranShiftHarian
{
    /**
     * @param  list<int>  $idOutlet
     * @return array<int, array{JumlahShiftTertutup: int, JumlahShiftBelumDitutup: int, KasAwal: string, KasSeharusnya: string, KasAktual: string, Selisih: string}> kunci = IdOutlet; outlet tanpa shift tidak ada
     */
    public function Ambil(CarbonInterface $tanggalBisnis, array $idOutlet): array
    {
        if ($idOutlet === []) {
            return [];
        }

        $hasil = [];
        $shift = Shift::query()
            ->where('TanggalBisnis', $tanggalBisnis->toDateString())
            ->whereIn('IdOutlet', $idOutlet)
            ->orderBy('Id')
            ->get(['Id', 'IdOutlet', 'Status', 'KasAwal', 'KasSeharusnya', 'KasAktual', 'Selisih']);

        foreach ($shift as $s) {
            $ada = $hasil[$s->IdOutlet] ?? [
                'JumlahShiftTertutup' => 0,
                'JumlahShiftBelumDitutup' => 0,
                'KasAwal' => Uang::Nol(),
                'KasSeharusnya' => Uang::Nol(),
                'KasAktual' => Uang::Nol(),
                'Selisih' => Uang::Nol(),
            ];

            if ($s->Status === StatusShift::Tertutup) {
                $ada['JumlahShiftTertutup']++;
                $ada['KasAwal'] = $ada['KasAwal']->Tambah(Uang::Dari($s->KasAwal));
                $ada['KasSeharusnya'] = $ada['KasSeharusnya']->Tambah(Uang::Dari($s->KasSeharusnya ?? '0'));
                $ada['KasAktual'] = $ada['KasAktual']->Tambah(Uang::Dari($s->KasAktual ?? '0'));
                $ada['Selisih'] = $ada['Selisih']->Tambah(Uang::Dari($s->Selisih ?? '0'));
            } else {
                $ada['JumlahShiftBelumDitutup']++;
            }

            $hasil[$s->IdOutlet] = $ada;
        }

        return array_map(fn (array $b): array => [
            ...$b,
            'KasAwal' => $b['KasAwal']->KeString(),
            'KasSeharusnya' => $b['KasSeharusnya']->KeString(),
            'KasAktual' => $b['KasAktual']->KeString(),
            'Selisih' => $b['Selisih']->KeString(),
        ], $hasil);
    }
}
