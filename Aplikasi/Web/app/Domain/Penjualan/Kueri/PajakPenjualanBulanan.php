<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Penjualan\Enum\StatusPenjualan;
use App\Domain\Penjualan\Layanan\PembagiPajakRetur;
use App\Domain\Penjualan\Model\PenjualanPajak;
use App\Domain\Penjualan\Model\ReturPenjualanDetail;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * Pajak keluaran penjualan per outlet × bulan (tanggal bisnis) × jenis pajak × tarif untuk laporan pajak F-14a
 * (TAX-04 PB1/PBJT, TAX-05 PPN dasar): DPP & pajak dari `PenjualanPajak` penjualan bukan void, dikurangi bagian retur
 * pada bulan & outlet returnya. Pajak retur per kode dibagi seperti jurnal retur (`PembagiPajakRetur`); DPP retur =
 * DPP dokumen × (pajak retur ÷ pajak dokumen) per kode, dibulatkan ke sen HalfUp.
 */
final class PajakPenjualanBulanan
{
    /**
     * @param  list<int>|null  $idOutlet  null = semua outlet
     * @return list<array{IdOutlet: int, Bulan: string, KodeJenisPajak: string, Tarif: string, Dpp: string, Pajak: string, DppRetur: string, PajakRetur: string, DppBersih: string, PajakBersih: string, JumlahTransaksi: int}>
     */
    public function Ambil(CarbonImmutable $dari, CarbonImmutable $sampai, ?array $idOutlet): array
    {
        if ($idOutlet === []) {
            return [];
        }

        $rentang = [$dari->toDateString(), $sampai->toDateString()];
        $hasil = [];

        $jual = PenjualanPajak::query()
            ->join('Penjualan', fn (JoinClause $j) => $j->on('Penjualan.Id', '=', 'PenjualanPajak.IdPenjualan')->on('Penjualan.IdTenant', '=', 'PenjualanPajak.IdTenant'))
            ->where('Penjualan.Status', '!=', StatusPenjualan::Void->value)
            ->whereBetween('Penjualan.TanggalBisnis', $rentang)
            ->when($idOutlet !== null, fn (Builder $k) => $k->whereIn('Penjualan.IdOutlet', $idOutlet ?? []))
            ->selectRaw("`Penjualan`.`IdOutlet` AS `IdOutlet`, DATE_FORMAT(`Penjualan`.`TanggalBisnis`, '%Y-%m') AS `Bulan`, `PenjualanPajak`.`KodeJenisPajak` AS `Kode`, `PenjualanPajak`.`Tarif` AS `Tarif`")
            ->selectRaw('COALESCE(SUM(`PenjualanPajak`.`Dpp`), 0) AS `Dpp`, COALESCE(SUM(`PenjualanPajak`.`Jumlah`), 0) AS `Pajak`, COUNT(DISTINCT `Penjualan`.`Id`) AS `Jumlah`')
            ->groupByRaw('`Penjualan`.`IdOutlet`, `Bulan`, `PenjualanPajak`.`KodeJenisPajak`, `PenjualanPajak`.`Tarif`')
            ->toBase()
            ->get();

        foreach ($jual as $b) {
            $kunci = self::Kunci((int) $b->IdOutlet, (string) $b->Bulan, (string) $b->Kode, (string) $b->Tarif);
            $hasil[$kunci] = [
                'IdOutlet' => (int) $b->IdOutlet,
                'Bulan' => (string) $b->Bulan,
                'KodeJenisPajak' => (string) $b->Kode,
                'Tarif' => (string) $b->Tarif,
                'Dpp' => Uang::Dari((string) $b->Dpp),
                'Pajak' => Uang::Dari((string) $b->Pajak),
                'DppRetur' => Uang::Nol(),
                'PajakRetur' => Uang::Nol(),
                'JumlahTransaksi' => (int) $b->Jumlah,
            ];
        }

        foreach ($this->AmbilPajakRetur($rentang, $idOutlet) as $r) {
            $kunci = self::Kunci($r['IdOutlet'], $r['Bulan'], $r['Kode'], $r['Tarif']);
            $ada = $hasil[$kunci] ?? [
                'IdOutlet' => $r['IdOutlet'],
                'Bulan' => $r['Bulan'],
                'KodeJenisPajak' => $r['Kode'],
                'Tarif' => $r['Tarif'],
                'Dpp' => Uang::Nol(),
                'Pajak' => Uang::Nol(),
                'DppRetur' => Uang::Nol(),
                'PajakRetur' => Uang::Nol(),
                'JumlahTransaksi' => 0,
            ];
            $ada['DppRetur'] = $ada['DppRetur']->Tambah($r['Dpp']);
            $ada['PajakRetur'] = $ada['PajakRetur']->Tambah($r['Pajak']);
            $hasil[$kunci] = $ada;
        }

        ksort($hasil);

        return array_values(array_map(fn (array $b): array => [
            'IdOutlet' => $b['IdOutlet'],
            'Bulan' => $b['Bulan'],
            'KodeJenisPajak' => $b['KodeJenisPajak'],
            'Tarif' => $b['Tarif'],
            'Dpp' => $b['Dpp']->KeString(),
            'Pajak' => $b['Pajak']->KeString(),
            'DppRetur' => $b['DppRetur']->KeString(),
            'PajakRetur' => $b['PajakRetur']->KeString(),
            'DppBersih' => $b['Dpp']->Kurangi($b['DppRetur'])->KeString(),
            'PajakBersih' => $b['Pajak']->Kurangi($b['PajakRetur'])->KeString(),
            'JumlahTransaksi' => $b['JumlahTransaksi'],
        ], $hasil));
    }

    /**
     * Pajak & DPP bagian retur per (outlet retur, bulan retur, kode, tarif penjualan asal).
     *
     * @param  array{0: string, 1: string}  $rentang
     * @param  list<int>|null  $idOutlet
     * @return list<array{IdOutlet: int, Bulan: string, Kode: string, Tarif: string, Dpp: Uang, Pajak: Uang}>
     */
    private function AmbilPajakRetur(array $rentang, ?array $idOutlet): array
    {
        $baris = ReturPenjualanDetail::query()
            ->join('ReturPenjualan', fn (JoinClause $j) => $j->on('ReturPenjualan.Id', '=', 'ReturPenjualanDetail.IdReturPenjualan')->on('ReturPenjualan.IdTenant', '=', 'ReturPenjualanDetail.IdTenant'))
            ->join('PenjualanDetail', fn (JoinClause $j) => $j->on('PenjualanDetail.Id', '=', 'ReturPenjualanDetail.IdPenjualanDetail')->on('PenjualanDetail.IdTenant', '=', 'ReturPenjualanDetail.IdTenant'))
            ->whereBetween('ReturPenjualan.TanggalBisnis', $rentang)
            ->where('ReturPenjualanDetail.Pajak', '!=', 0)
            ->when($idOutlet !== null, fn (Builder $k) => $k->whereIn('ReturPenjualan.IdOutlet', $idOutlet ?? []))
            ->orderBy('ReturPenjualanDetail.Id')
            ->toBase()
            ->get([
                'ReturPenjualan.IdOutlet AS IdOutlet',
                'ReturPenjualan.TanggalBisnis AS Tanggal',
                'ReturPenjualanDetail.Pajak AS Pajak',
                'PenjualanDetail.IdPenjualan AS IdPenjualan',
                'PenjualanDetail.SnapshotPajak AS Snapshot',
            ]);

        if ($baris->isEmpty()) {
            return [];
        }

        $pajakDokumen = [];

        foreach (PenjualanPajak::query()->whereIn('IdPenjualan', $baris->pluck('IdPenjualan')->unique()->values()->all())->orderBy('Id')->get() as $p) {
            $pajakDokumen[$p->IdPenjualan][$p->KodeJenisPajak] = $p;
        }

        $hasil = [];

        foreach ($baris as $b) {
            $dokumen = $pajakDokumen[(int) $b->IdPenjualan] ?? [];
            $snapshot = is_string($b->Snapshot) ? json_decode($b->Snapshot, true) : null;
            $bagian = PembagiPajakRetur::Bagi(Uang::Dari((string) $b->Pajak), is_array($snapshot) ? array_values($snapshot) : null, (string) (array_key_first($dokumen) ?? ''));
            $bulan = substr((string) $b->Tanggal, 0, 7);

            foreach ($bagian as $kode => $pajak) {
                $kode = (string) $kode;
                $asal = $dokumen[$kode] ?? null;
                $tarif = $asal !== null ? (string) $asal->Tarif : '0.000000';
                $dpp = $asal !== null && ! Uang::Dari($asal->Jumlah)->BernilaiNol()
                    ? Uang::Dari(BigDecimal::of($asal->Dpp)->multipliedBy($pajak->KeString())->dividedBy($asal->Jumlah, Uang::SKALA, RoundingMode::HalfUp))
                    : Uang::Nol();
                $kunci = self::Kunci((int) $b->IdOutlet, $bulan, $kode, $tarif);
                $ada = $hasil[$kunci] ?? ['IdOutlet' => (int) $b->IdOutlet, 'Bulan' => $bulan, 'Kode' => $kode, 'Tarif' => $tarif, 'Dpp' => Uang::Nol(), 'Pajak' => Uang::Nol()];
                $ada['Dpp'] = $ada['Dpp']->Tambah($dpp);
                $ada['Pajak'] = $ada['Pajak']->Tambah($pajak);
                $hasil[$kunci] = $ada;
            }
        }

        return array_values($hasil);
    }

    private static function Kunci(int $idOutlet, string $bulan, string $kode, string $tarif): string
    {
        return "{$bulan}|{$idOutlet}|{$kode}|{$tarif}";
    }
}
