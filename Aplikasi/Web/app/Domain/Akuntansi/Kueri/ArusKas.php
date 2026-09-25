<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Data\SaringLaporanKeuangan;
use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Enum\TipeAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\JurnalDetail;
use App\Domain\Bersama\Nilai\Uang;
use Illuminate\Database\Eloquent\Builder;

/**
 * Arus kas metode langsung (F-13, FIN-07 P1, tipe FE `PropsArusKas`) dari baris `JurnalDetail` akun kas & bank
 * (`Akun.KasBank`). Setiap jurnal menyumbang perubahan kas bersihnya (Σ debit − kredit baris kas; transfer antar kas
 * bernilai nol dan tidak tampil). Jurnal kas & bank dan kas masuk/keluar shift dirinci per **akun lawan** (satu akun
 * non-kas), jurnal lain per **jenis sumber** (penjualan, pembayaran hutang, …). Klasifikasi aktivitas (keputusan agen,
 * SAK EMKM): akun ekuitas & kewajiban jangka panjang → pendanaan, aset tidak lancar → investasi, sisanya → operasi;
 * lancar/tidak lancar dibaca dari digit pertama sesudah tanda hubung kode akun (`x-1xxx` lancar, `x-2xxx` dst. tidak
 * lancar, §11.2). Kas awal + kenaikan bersih = kas akhir (dengan saringan outlet yang sama).
 */
final class ArusKas
{
    private const AKTIVITAS = [
        'Operasi' => ['Label' => 'Aktivitas operasi', 'LabelTotal' => 'Arus kas bersih dari aktivitas operasi'],
        'Investasi' => ['Label' => 'Aktivitas investasi', 'LabelTotal' => 'Arus kas bersih dari aktivitas investasi'],
        'Pendanaan' => ['Label' => 'Aktivitas pendanaan', 'LabelTotal' => 'Arus kas bersih dari aktivitas pendanaan'],
    ];

    /** Sumber yang dirinci per akun lawan (jurnal dua baris: kas + satu akun lain). */
    private const SUMBER_PER_AKUN = [JenisSumberJurnal::TransaksiKasBank, JenisSumberJurnal::MutasiKas];

    /**
     * @return array{Periode: array{Dari: string, Sampai: string}, Baris: list<array<string, mixed>>, Ringkasan: array<string, string>, AdaAkunKas: bool}
     */
    public function Ambil(SaringLaporanKeuangan $saring): array
    {
        $idKas = array_values(Akun::query()->where('KasBank', true)->pluck('Id')->map(fn ($id): int => (int) $id)->all());
        $nol = ['Operasi' => [], 'Investasi' => [], 'Pendanaan' => []];

        if ($idKas === []) {
            return $this->Susun($saring, $nol, Uang::Nol(), Uang::Nol(), false);
        }

        $saldoAwal = $this->JumlahkanKas($saring, $idKas, fn ($k) => $k->where('Tanggal', '<', $saring->dari));
        $rincian = $nol;

        // 1) Jurnal selain sumber per akun: dijumlah per jenis sumber di SQL.
        $perSumber = $this->KueriKas($saring, $idKas)
            ->whereNotIn('Jurnal.JenisSumber', array_map(fn (JenisSumberJurnal $j): string => $j->value, self::SUMBER_PER_AKUN))
            ->groupBy('Jurnal.JenisSumber')
            ->selectRaw('`Jurnal`.`JenisSumber`, CAST(SUM(`JurnalDetail`.`Debit` - `JurnalDetail`.`Kredit`) AS DECIMAL(20,2)) AS `Nilai`')
            ->toBase()
            ->get();

        foreach ($perSumber as $satu) {
            $jenis = JenisSumberJurnal::tryFrom((string) $satu->JenisSumber);
            $this->Tambahkan($rincian, 'Operasi', 'Sumber|'.$satu->JenisSumber, self::LabelSumber($jenis, (string) $satu->JenisSumber), Uang::Dari((string) $satu->Nilai), null);
        }

        // 2) Jurnal kas & bank / kas shift: perubahan kas per jurnal, lalu dirinci per akun lawan.
        $perJurnal = $this->KueriKas($saring, $idKas)
            ->whereIn('Jurnal.JenisSumber', array_map(fn (JenisSumberJurnal $j): string => $j->value, self::SUMBER_PER_AKUN))
            ->groupBy('JurnalDetail.IdJurnal', 'Jurnal.JenisSumber')
            ->selectRaw('`JurnalDetail`.`IdJurnal`, `Jurnal`.`JenisSumber`, CAST(SUM(`JurnalDetail`.`Debit` - `JurnalDetail`.`Kredit`) AS DECIMAL(20,2)) AS `Nilai`')
            ->havingRaw('SUM(`JurnalDetail`.`Debit` - `JurnalDetail`.`Kredit`) <> 0')
            ->toBase()
            ->get();
        $lawan = $perJurnal->isEmpty() ? collect() : JurnalDetail::query()
            ->whereIn('IdJurnal', $perJurnal->pluck('IdJurnal')->all())
            ->whereNotIn('IdAkun', $idKas)
            ->toBase()
            ->get(['IdJurnal', 'IdAkun'])
            ->groupBy('IdJurnal');
        $akun = Akun::query()->whereKey($lawan->flatten(1)->pluck('IdAkun')->unique()->values()->all())->get()->keyBy('Id');

        foreach ($perJurnal as $satu) {
            $barisLawan = $lawan->get($satu->IdJurnal);
            $idAkunLawan = $barisLawan === null ? collect() : $barisLawan->pluck('IdAkun')->unique()->values();
            $nilai = Uang::Dari((string) $satu->Nilai);
            $akunLawan = $idAkunLawan->count() === 1 ? $akun->get($idAkunLawan->first()) : null;

            if ($akunLawan instanceof Akun) {
                $this->Tambahkan($rincian, self::KlasifikasiAkun($akunLawan), 'Akun|'.$akunLawan->Uuid, $akunLawan->Nama, $nilai, $akunLawan->Kode);

                continue;
            }

            $jenis = JenisSumberJurnal::tryFrom((string) $satu->JenisSumber);
            $this->Tambahkan($rincian, 'Operasi', 'Sumber|'.$satu->JenisSumber, self::LabelSumber($jenis, (string) $satu->JenisSumber), $nilai, null);
        }

        $kenaikan = $this->JumlahkanKas($saring, $idKas, fn ($k) => $k->whereBetween('Tanggal', [$saring->dari, $saring->sampai]));

        return $this->Susun($saring, $rincian, $saldoAwal, $kenaikan, true);
    }

    /**
     * Klasifikasi akun lawan ke aktivitas arus kas (lihat docblock kelas).
     */
    public static function KlasifikasiAkun(Akun $akun): string
    {
        $tidakLancar = (int) substr((string) strstr($akun->Kode, '-'), 1, 1) >= 2;

        return match (true) {
            $akun->Jenis === TipeAkun::Ekuitas => 'Pendanaan',
            $akun->Jenis === TipeAkun::Kewajiban && $tidakLancar => 'Pendanaan',
            $akun->Jenis === TipeAkun::Aset && $tidakLancar => 'Investasi',
            default => 'Operasi',
        };
    }

    /**
     * @param  list<int>  $idKas
     * @return Builder<JurnalDetail>
     */
    private function KueriKas(SaringLaporanKeuangan $saring, array $idKas): Builder
    {
        $kueri = JurnalDetail::query()
            ->join('Jurnal', fn ($j) => $j->on('Jurnal.Id', '=', 'JurnalDetail.IdJurnal')->on('Jurnal.IdTenant', '=', 'JurnalDetail.IdTenant'))
            ->whereIn('JurnalDetail.IdAkun', $idKas)
            ->whereBetween('JurnalDetail.Tanggal', [$saring->dari, $saring->sampai]);

        return $saring->TerapkanOutlet($kueri, 'JurnalDetail.IdOutlet');
    }

    /**
     * Σ (debit − kredit) baris kas yang lolos saringan tanggal tambahan.
     *
     * @param  list<int>  $idKas
     * @param  callable(Builder<JurnalDetail>): mixed  $tanggal
     */
    private function JumlahkanKas(SaringLaporanKeuangan $saring, array $idKas, callable $tanggal): Uang
    {
        $kueri = JurnalDetail::query()->whereIn('IdAkun', $idKas);
        $tanggal($kueri);
        $nilai = $saring->TerapkanOutlet($kueri)
            ->selectRaw('CAST(COALESCE(SUM(`Debit` - `Kredit`), 0) AS DECIMAL(20,2)) AS `Nilai`')
            ->toBase()
            ->value('Nilai');

        return Uang::Dari((string) ($nilai ?? '0'));
    }

    /**
     * @param  array<string, array<string, array{Id: string, Label: string, Kode: string|null, Nilai: Uang}>>  $rincian
     */
    private function Tambahkan(array &$rincian, string $aktivitas, string $id, string $label, Uang $nilai, ?string $kode): void
    {
        $lama = $rincian[$aktivitas][$id]['Nilai'] ?? Uang::Nol();
        $rincian[$aktivitas][$id] = ['Id' => $id, 'Label' => $label, 'Kode' => $kode, 'Nilai' => $lama->Tambah($nilai)];
    }

    private static function LabelSumber(?JenisSumberJurnal $jenis, string $mentah): string
    {
        return match ($jenis) {
            JenisSumberJurnal::Penjualan => 'Penerimaan dari penjualan',
            JenisSumberJurnal::ReturPenjualan => 'Pengembalian dana retur penjualan',
            JenisSumberJurnal::PembayaranHutang => 'Pembayaran ke pemasok',
            JenisSumberJurnal::PenerimaanBarang => 'Belanja stok tunai',
            JenisSumberJurnal::TransaksiKasBank => 'Transfer antar akun kas & bank',
            JenisSumberJurnal::MutasiKas => 'Setoran kas outlet ke brankas',
            null => $mentah,
            default => $jenis->AmbilLabel(),
        };
    }

    /**
     * @param  array<string, array<string, array{Id: string, Label: string, Kode: string|null, Nilai: Uang}>>  $rincian
     * @return array{Periode: array{Dari: string, Sampai: string}, Baris: list<array<string, mixed>>, Ringkasan: array<string, string>, AdaAkunKas: bool}
     */
    private function Susun(SaringLaporanKeuangan $saring, array $rincian, Uang $saldoAwal, Uang $kenaikan, bool $adaAkunKas): array
    {
        $baris = [];
        $ringkasan = [];

        foreach (self::AKTIVITAS as $kunci => $aktivitas) {
            $total = Uang::Nol();
            $baris[] = self::Baris('Kepala|'.$kunci, 'Kepala', $kunci, $aktivitas['Label'], null);
            $isi = array_values(array_filter($rincian[$kunci], fn (array $r): bool => ! $r['Nilai']->BernilaiNol()));
            usort($isi, fn (array $a, array $b): int => [$a['Kode'] === null ? 0 : 1, $a['Kode'] ?? '', $a['Label']] <=> [$b['Kode'] === null ? 0 : 1, $b['Kode'] ?? '', $b['Label']]);

            foreach ($isi as $r) {
                $total = $total->Tambah($r['Nilai']);
                $baris[] = self::Baris($r['Id'], 'Rincian', $kunci, $r['Label'], $r['Nilai'], $r['Kode']);
            }

            $baris[] = self::Baris('Subtotal|'.$kunci, 'Subtotal', $kunci, $aktivitas['LabelTotal'], $total);
            $ringkasan[$kunci] = $total->KeString();
        }

        $saldoAkhir = $saldoAwal->Tambah($kenaikan);
        $baris[] = self::Baris('Total|Kenaikan', 'Total', 'Kenaikan', 'Kenaikan (penurunan) bersih kas & bank', $kenaikan);
        $baris[] = self::Baris('Total|Awal', 'Saldo', 'Awal', 'Kas & bank awal periode', $saldoAwal);
        $baris[] = self::Baris('Total|Akhir', 'Total', 'Akhir', 'Kas & bank akhir periode', $saldoAkhir);

        return [
            'Periode' => ['Dari' => $saring->dari, 'Sampai' => $saring->sampai],
            'Baris' => $baris,
            'Ringkasan' => $ringkasan + ['Kenaikan' => $kenaikan->KeString(), 'SaldoAwal' => $saldoAwal->KeString(), 'SaldoAkhir' => $saldoAkhir->KeString()],
            'AdaAkunKas' => $adaAkunKas,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function Baris(string $id, string $jenis, string $aktivitas, string $label, ?Uang $nilai, ?string $kode = null): array
    {
        return ['Id' => $id, 'Jenis' => $jenis, 'Aktivitas' => $aktivitas, 'Kode' => $kode, 'Label' => $label, 'Nilai' => $nilai?->KeString()];
    }
}
