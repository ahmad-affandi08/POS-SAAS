<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Enum\StatusMutasiBank;
use App\Domain\Akuntansi\Layanan\PencariKandidatMutasi;
use App\Domain\Akuntansi\Model\JurnalDetail;
use App\Domain\Akuntansi\Model\MutasiBank;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use Illuminate\Support\Collection;

/**
 * Data halaman rekonsiliasi bank (FIN-09) untuk satu akun kas/bank: ringkasan saldo rekening koran vs saldo buku,
 * daftar mutasi (`TabelData`; cari keterangan, saring status & tanggal) beserta kandidat jurnal untuk yang belum cocok,
 * dan baris buku di rentang rekening koran yang belum ada pasangannya (setoran/cek dalam perjalanan, atau salah catat).
 */
final class RekonsiliasiBank
{
    public const KOLOM_URUT = ['Tanggal', 'Masuk', 'Keluar'];

    public const KOLOM_SARING = ['Status', 'Tanggal'];

    public const URUT_BAWAAN = '-Tanggal';

    public function __construct(private readonly PencariKandidatMutasi $kandidat) {}

    /**
     * @return array<string, mixed>
     */
    public function AmbilRingkasan(int $idAkun): array
    {
        $jumlah = MutasiBank::query()->where('IdAkun', $idAkun)->groupBy('Status')->selectRaw('Status, COUNT(*) AS N')->pluck('N', 'Status');
        $terakhir = MutasiBank::query()->where('IdAkun', $idAkun)->orderByDesc('Tanggal')->orderByDesc('Id')->first();
        $saldoKoran = MutasiBank::query()->where('IdAkun', $idAkun)->whereNotNull('Saldo')->orderByDesc('Tanggal')->orderByDesc('Id')->value('Saldo');
        $saldoBuku = null;

        if ($terakhir !== null) {
            $saldoBuku = Uang::Dari((string) (JurnalDetail::query()->where('IdAkun', $idAkun)->where('Tanggal', '<=', $terakhir->Tanggal->toDateString())
                ->selectRaw('CAST(COALESCE(SUM(`Debit` - `Kredit`), 0) AS DECIMAL(20,2)) AS s')->value('s') ?? '0'));
        }

        return [
            'BelumCocok' => (int) ($jumlah[StatusMutasiBank::BelumCocok->value] ?? 0),
            'Cocok' => (int) ($jumlah[StatusMutasiBank::Cocok->value] ?? 0),
            'Diabaikan' => (int) ($jumlah[StatusMutasiBank::Diabaikan->value] ?? 0),
            'TanggalTerakhir' => $terakhir?->Tanggal->toDateString(),
            'SaldoRekeningKoran' => $saldoKoran === null ? null : (string) $saldoKoran,
            'SaldoBuku' => $saldoBuku?->KeString(),
            'Selisih' => $saldoKoran === null || $saldoBuku === null ? null : Uang::Dari((string) $saldoKoran)->Kurangi($saldoBuku)->KeString(),
        ];
    }

    /**
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilMutasi(DataPermintaanTabel $p, int $idAkun): array
    {
        $status = $p->AmbilDaftar('Status', array_map(fn (StatusMutasiBank $s): string => $s->value, StatusMutasiBank::cases()));
        ['Dari' => $dari, 'Sampai' => $sampai] = $p->AmbilRentangTanggal('Tanggal');
        $kueri = MutasiBank::query()->where('IdAkun', $idAkun)
            ->when($status !== [], fn ($k) => $k->whereIn('Status', $status))
            ->when($dari !== null, fn ($k) => $k->where('Tanggal', '>=', $dari))
            ->when($sampai !== null, fn ($k) => $k->where('Tanggal', '<=', $sampai))
            ->when($p->cari !== '', fn ($k) => $k->where('Keterangan', 'like', PenerapKueriTabel::PolaCari($p->cari)));

        return PenerapKueriTabel::Terapkan($kueri, $p, array_combine(self::KOLOM_URUT, self::KOLOM_URUT), function (Collection $baris): array {
            /** @var Collection<int, MutasiBank> $baris */
            $pasangan = JurnalDetail::query()->whereKey(array_values(array_filter($baris->map(fn (MutasiBank $m): ?int => $m->IdJurnalDetail)->all(), 'is_int')))->with('Jurnal:Id,Uuid,Nomor,Keterangan')->get()->keyBy('Id');

            return array_values($baris->map(function (MutasiBank $m) use ($pasangan): array {
                $cocok = $m->IdJurnalDetail === null ? null : $pasangan->get($m->IdJurnalDetail);

                return [
                    'Uuid' => $m->Uuid,
                    'Tanggal' => $m->Tanggal->toDateString(),
                    'Keterangan' => $m->Keterangan,
                    'Masuk' => (string) $m->Masuk,
                    'Keluar' => (string) $m->Keluar,
                    'Saldo' => $m->Saldo === null ? null : (string) $m->Saldo,
                    'Status' => $m->Status->value,
                    'LabelStatus' => $m->Status->AmbilLabel(),
                    'AlasanAbaikan' => $m->AlasanAbaikan,
                    'Jurnal' => $cocok === null ? null : self::PetakanJurnal($cocok),
                    'Kandidat' => $m->Status === StatusMutasiBank::BelumCocok
                        ? array_values($this->kandidat->Cari($m, 5)->map(fn (JurnalDetail $d): array => self::PetakanJurnal($d))->all())
                        : [],
                ];
            })->all());
        });
    }

    /**
     * Baris buku akun ini di rentang tanggal rekening koran yang belum punya pasangan mutasi (maks. 200).
     *
     * @return list<array<string, mixed>>
     */
    public function AmbilBukuBelumCocok(int $idAkun): array
    {
        $rentang = MutasiBank::query()->where('IdAkun', $idAkun)->selectRaw('MIN(Tanggal) AS Awal, MAX(Tanggal) AS Akhir')->first();
        $awal = $rentang?->getAttribute('Awal');
        $akhir = $rentang?->getAttribute('Akhir');

        if (! is_string($awal) || ! is_string($akhir)) {
            return [];
        }

        return array_values(JurnalDetail::query()
            ->where('IdAkun', $idAkun)
            ->whereBetween('Tanggal', [$awal, $akhir])
            ->whereNotIn('Id', MutasiBank::query()->whereNotNull('IdJurnalDetail')->select('IdJurnalDetail'))
            ->with('Jurnal:Id,Uuid,Nomor,Keterangan,Tanggal')
            ->orderBy('Tanggal')
            ->orderBy('Id')
            ->limit(200)
            ->get()
            ->map(fn (JurnalDetail $d): array => self::PetakanJurnal($d))
            ->all());
    }

    /**
     * @return array{Uuid: string, Nomor: string, Keterangan: string, Tanggal: string, Debit: string, Kredit: string}
     */
    private static function PetakanJurnal(JurnalDetail $d): array
    {
        return [
            'Uuid' => $d->Jurnal->Uuid,
            'Nomor' => $d->Jurnal->Nomor,
            'Keterangan' => $d->Memo !== null && $d->Memo !== '' ? $d->Jurnal->Keterangan.' — '.$d->Memo : $d->Jurnal->Keterangan,
            'Tanggal' => $d->Tanggal->toDateString(),
            'Debit' => (string) $d->Debit,
            'Kredit' => (string) $d->Kredit,
        ];
    }
}
