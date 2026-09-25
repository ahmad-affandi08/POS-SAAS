<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Data\SaringLaporanKeuangan;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\SaldoNormal;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\JurnalDetail;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use Illuminate\Database\Eloquent\Builder;
use stdClass;

/**
 * Buku besar satu akun (F-13a, FIN-06, tipe FE `BarisBukuBesar`): saldo awal (jurnal sebelum `dari`), mutasi dalam
 * rentang urut tanggal lalu jurnal, dan saldo berjalan per baris dihitung MySQL (`SUM() OVER`) sebelum paginasi,
 * sehingga halaman mana pun benar tanpa memuat semua baris. Saldo mengikuti saldo normal akun (positif = sisi
 * normal). Tautan ke jurnal dan dokumen sumbernya. Uang sebagai string desimal.
 */
final class BukuBesar
{
    public function __construct(private readonly PetaUuidOutlet $outlet) {}

    public function CariAkun(string $uuid): ?Akun
    {
        return Akun::query()->where('Uuid', $uuid)->first();
    }

    /**
     * Semua akun (termasuk nonaktif, karena jurnal lamanya tetap bisa dilihat) untuk pilihan akun.
     *
     * @return list<array{Nilai: string, Label: string}>
     */
    public function AmbilOpsiAkun(): array
    {
        return array_values(Akun::query()->orderBy('Kode')->get(['Uuid', 'Kode', 'Nama', 'Aktif'])
            ->map(fn (Akun $a): array => ['Nilai' => $a->Uuid, 'Label' => $a->AmbilLabel().($a->Aktif ? '' : ' (nonaktif)')])
            ->all());
    }

    /**
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}, Ringkasan: array{SaldoAwal: string, TotalDebit: string, TotalKredit: string, SaldoAkhir: string}}
     */
    public function AmbilTabel(Akun $akun, SaringLaporanKeuangan $saring, DataPermintaanTabel $permintaan): array
    {
        $ringkasan = $this->AmbilRingkasan($akun, $saring);
        $total = $ringkasan['Jumlah'];
        $jumlahHalaman = max(1, intdiv($total + $permintaan->perHalaman - 1, $permintaan->perHalaman));
        $halaman = min($permintaan->halaman, $jumlahHalaman);
        $baris = array_values($this->KueriMutasi($akun, $saring)
            ->offset(($halaman - 1) * $permintaan->perHalaman)
            ->limit($permintaan->perHalaman)
            ->toBase()
            ->get()
            ->all());

        return [
            'Data' => $this->Petakan($akun, $ringkasan['SaldoAwalMentah'], $baris, $this->AmbilNamaOutlet($baris)),
            'Meta' => ['Halaman' => $halaman, 'PerHalaman' => $permintaan->perHalaman, 'Total' => $total, 'JumlahHalaman' => $jumlahHalaman],
            'Ringkasan' => [
                'SaldoAwal' => self::Normal($akun, $ringkasan['SaldoAwalMentah'])->KeString(),
                'TotalDebit' => $ringkasan['Debit']->KeString(),
                'TotalKredit' => $ringkasan['Kredit']->KeString(),
                'SaldoAkhir' => self::Normal($akun, $ringkasan['SaldoAwalMentah']->Tambah($ringkasan['Debit'])->Kurangi($ringkasan['Kredit']))->KeString(),
            ],
        ];
    }

    /**
     * Semua baris untuk ekspor, dialirkan per potongan (cursor) tanpa memuat semuanya ke memori.
     *
     * @return iterable<array<string, mixed>>
     */
    public function AmbilSemua(Akun $akun, SaringLaporanKeuangan $saring): iterable
    {
        $awal = $this->AmbilRingkasan($akun, $saring)['SaldoAwalMentah'];
        $namaOutlet = array_column($this->outlet->AmbilRingkas(), 'Nama', 'Id');

        foreach ($this->KueriMutasi($akun, $saring)->toBase()->cursor() as $baris) {
            yield from $this->Petakan($akun, $awal, [$baris], $namaOutlet);
        }
    }

    /**
     * @return array{SaldoAwalMentah: Uang, Debit: Uang, Kredit: Uang, Jumlah: int}
     */
    private function AmbilRingkasan(Akun $akun, SaringLaporanKeuangan $saring): array
    {
        $hasil = $saring->TerapkanOutlet(JurnalDetail::query()->where('IdAkun', $akun->Id)->where('Tanggal', '<=', $saring->sampai))
            ->selectRaw(
                'CAST(COALESCE(SUM(CASE WHEN `Tanggal` < ? THEN `Debit` - `Kredit` ELSE 0 END), 0) AS DECIMAL(20,2)) AS `SaldoAwal`,'
                .' CAST(COALESCE(SUM(CASE WHEN `Tanggal` >= ? THEN `Debit` ELSE 0 END), 0) AS DECIMAL(20,2)) AS `Debit`,'
                .' CAST(COALESCE(SUM(CASE WHEN `Tanggal` >= ? THEN `Kredit` ELSE 0 END), 0) AS DECIMAL(20,2)) AS `Kredit`,'
                .' SUM(CASE WHEN `Tanggal` >= ? THEN 1 ELSE 0 END) AS `Jumlah`',
                [$saring->dari, $saring->dari, $saring->dari, $saring->dari],
            )
            ->toBase()
            ->first();

        return [
            'SaldoAwalMentah' => Uang::Dari((string) ($hasil->SaldoAwal ?? '0')),
            'Debit' => Uang::Dari((string) ($hasil->Debit ?? '0')),
            'Kredit' => Uang::Dari((string) ($hasil->Kredit ?? '0')),
            'Jumlah' => (int) ($hasil->Jumlah ?? 0),
        ];
    }

    /**
     * Mutasi dalam rentang + kumulatif (debit − kredit) sampai baris itu, urut tanggal, jurnal, lalu baris.
     *
     * @return Builder<JurnalDetail>
     */
    private function KueriMutasi(Akun $akun, SaringLaporanKeuangan $saring): Builder
    {
        $kueri = JurnalDetail::query()
            ->join('Jurnal', fn ($j) => $j->on('Jurnal.Id', '=', 'JurnalDetail.IdJurnal')->on('Jurnal.IdTenant', '=', 'JurnalDetail.IdTenant'))
            ->where('JurnalDetail.IdAkun', $akun->Id)
            ->whereBetween('JurnalDetail.Tanggal', [$saring->dari, $saring->sampai]);
        $saring->TerapkanOutlet($kueri, 'JurnalDetail.IdOutlet');

        return $kueri
            ->select([
                'JurnalDetail.Id', 'JurnalDetail.Tanggal', 'JurnalDetail.Debit', 'JurnalDetail.Kredit', 'JurnalDetail.Memo',
                'JurnalDetail.IdOutlet', 'Jurnal.Uuid AS UuidJurnal', 'Jurnal.Nomor AS NomorJurnal', 'Jurnal.Keterangan',
                'Jurnal.JenisSumber', 'Jurnal.UuidSumber', 'Jurnal.NomorSumber',
            ])
            ->selectRaw('CAST(SUM(`JurnalDetail`.`Debit` - `JurnalDetail`.`Kredit`) OVER (ORDER BY `JurnalDetail`.`Tanggal`, `JurnalDetail`.`IdJurnal`, `JurnalDetail`.`Id`) AS DECIMAL(20,2)) AS `Kumulatif`')
            ->orderBy('JurnalDetail.Tanggal')
            ->orderBy('JurnalDetail.IdJurnal')
            ->orderBy('JurnalDetail.Id');
    }

    /**
     * @param  list<mixed>  $baris
     * @return array<int, string>
     */
    private function AmbilNamaOutlet(array $baris): array
    {
        $idOutlet = [];

        foreach ($baris as $satu) {
            if ($satu instanceof stdClass && $satu->IdOutlet !== null) {
                $idOutlet[] = (int) $satu->IdOutlet;
            }
        }

        return $idOutlet === [] ? [] : array_column($this->outlet->AmbilRingkas(array_values(array_unique($idOutlet))), 'Nama', 'Id');
    }

    /**
     * @param  list<mixed>  $baris
     * @param  array<int, string>  $namaOutlet
     * @return list<array<string, mixed>>
     */
    private function Petakan(Akun $akun, Uang $saldoAwal, array $baris, array $namaOutlet): array
    {
        $hasil = [];

        foreach ($baris as $satu) {
            if (! $satu instanceof stdClass) {
                continue;
            }

            $jenis = JenisSumberJurnal::tryFrom((string) $satu->JenisSumber);
            $hasil[] = [
                'Id' => (string) $satu->Id,
                'Tanggal' => substr((string) $satu->Tanggal, 0, 10),
                'UuidJurnal' => (string) $satu->UuidJurnal,
                'NomorJurnal' => (string) $satu->NomorJurnal,
                'Keterangan' => (string) $satu->Keterangan,
                'Memo' => $satu->Memo === null ? null : (string) $satu->Memo,
                'LabelSumber' => $jenis?->AmbilLabel() ?? (string) $satu->JenisSumber,
                'NomorSumber' => $satu->NomorSumber === null ? null : (string) $satu->NomorSumber,
                'TautanSumber' => $jenis?->BuatTautan($satu->UuidSumber === null ? null : (string) $satu->UuidSumber),
                'NamaOutlet' => $satu->IdOutlet === null ? null : ($namaOutlet[(int) $satu->IdOutlet] ?? null),
                'Debit' => Uang::Dari((string) $satu->Debit)->KeString(),
                'Kredit' => Uang::Dari((string) $satu->Kredit)->KeString(),
                'Saldo' => self::Normal($akun, $saldoAwal->Tambah(Uang::Dari((string) $satu->Kumulatif)))->KeString(),
            ];
        }

        return $hasil;
    }

    /** Saldo mentah (debit − kredit) menjadi saldo menurut saldo normal akun (positif = sisi normal). */
    private static function Normal(Akun $akun, Uang $mentah): Uang
    {
        return $akun->SaldoNormal === SaldoNormal::Debit ? $mentah : Uang::Nol()->Kurangi($mentah);
    }
}
