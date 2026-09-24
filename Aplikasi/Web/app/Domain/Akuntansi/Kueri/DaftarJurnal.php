<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Model\Jurnal;
use App\Domain\Akuntansi\Model\JurnalDetail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as BuilderKueri;

/**
 * Daftar jurnal berhalaman untuk halaman Jurnal (tipe FE `PropsDaftarJurnal`, DesainF05a C.5/E), terbaru di atas.
 * Saringan: kata (nomor jurnal, nomor sumber, keterangan), rentang tanggal, jenis sumber. Pengguna yang aksesnya
 * dibatasi ke outlet tertentu hanya melihat jurnal yang semua barisnya berada di outlet aksesnya.
 */
final class DaftarJurnal
{
    public const PER_HALAMAN = 50;

    /**
     * @param  array{Kata: string, Dari: string, Sampai: string, JenisSumber: string|null}  $saring
     * @param  list<int>|null  $idOutletBoleh  null = semua outlet
     * @return array{Data: list<array<string, mixed>>, HalamanSaatIni: int, HalamanTerakhir: int, Total: int}
     */
    public function Ambil(array $saring, int $halaman, ?array $idOutletBoleh = null): array
    {
        $kata = trim($saring['Kata']);
        $jenis = $saring['JenisSumber'] === null ? null : JenisSumberJurnal::tryFrom($saring['JenisSumber']);

        $kueri = Jurnal::query()
            ->when($kata !== '', function (Builder $kueri) use ($kata): void {
                $pola = '%'.addcslashes($kata, '%_\\').'%';
                $kueri->where(fn (Builder $k) => $k->where('Nomor', 'like', $pola)
                    ->orWhere('NomorSumber', 'like', $pola)
                    ->orWhere('Keterangan', 'like', $pola));
            })
            ->when(self::CekTanggal($saring['Dari']), fn (Builder $k) => $k->where('Tanggal', '>=', $saring['Dari']))
            ->when(self::CekTanggal($saring['Sampai']), fn (Builder $k) => $k->where('Tanggal', '<=', $saring['Sampai']))
            ->when($jenis !== null, fn (Builder $k) => $k->where('JenisSumber', $jenis?->value));

        self::BatasiOutlet($kueri, $idOutletBoleh);

        $hasil = $kueri->orderByDesc('Tanggal')->orderByDesc('Id')
            ->paginate(self::PER_HALAMAN, ['*'], 'halaman', max(1, $halaman));

        /** @var list<Jurnal> $jurnal */
        $jurnal = array_values($hasil->items());
        $idDibalik = self::AmbilIdYangDibalik(array_map(fn (Jurnal $j): int => $j->Id, $jurnal));

        return [
            'Data' => array_map(fn (Jurnal $j): array => self::PetakanBaris($j, isset($idDibalik[$j->Id])), $jurnal),
            'HalamanSaatIni' => $hasil->currentPage(),
            'HalamanTerakhir' => $hasil->lastPage(),
            'Total' => $hasil->total(),
        ];
    }

    /**
     * Baris daftar (`BarisDaftarJurnal`). `Dibalik` = sudah ada jurnal pembaliknya; `Pembalik` = jurnal ini pembalik.
     *
     * @return array<string, mixed>
     */
    public static function PetakanBaris(Jurnal $jurnal, bool $dibalik): array
    {
        return [
            'Uuid' => $jurnal->Uuid,
            'Nomor' => $jurnal->Nomor,
            'Tanggal' => $jurnal->Tanggal->toDateString(),
            'JenisSumber' => $jurnal->JenisSumber->value,
            'LabelJenisSumber' => $jurnal->JenisSumber->AmbilLabel(),
            'NomorSumber' => $jurnal->NomorSumber,
            'TautanSumber' => $jurnal->JenisSumber->BuatTautan($jurnal->UuidSumber),
            'Keterangan' => $jurnal->Keterangan,
            'TotalDebit' => $jurnal->TotalDebit,
            'Otomatis' => $jurnal->Otomatis,
            'Dibalik' => $dibalik,
            'Pembalik' => $jurnal->IdJurnalDibalik !== null,
        ];
    }

    /**
     * Jurnal dengan baris di luar outlet akses (atau tanpa outlet = tingkat usaha) disembunyikan.
     *
     * @param  Builder<Jurnal>  $kueri
     * @param  list<int>|null  $idOutletBoleh
     */
    public static function BatasiOutlet(Builder $kueri, ?array $idOutletBoleh): void
    {
        if ($idOutletBoleh === null) {
            return;
        }

        $kueri->whereNotExists(function (BuilderKueri $sub) use ($idOutletBoleh): void {
            $sub->from((new JurnalDetail)->getTable(), 'd')
                ->whereColumn('d.IdJurnal', 'Jurnal.Id')
                ->whereColumn('d.IdTenant', 'Jurnal.IdTenant')
                ->where(fn (BuilderKueri $k) => $k->whereNull('d.IdOutlet')->orWhereNotIn('d.IdOutlet', $idOutletBoleh === [] ? [0] : $idOutletBoleh));
        });
    }

    /**
     * @param  list<int>  $idJurnal
     * @return array<int, true>
     */
    private static function AmbilIdYangDibalik(array $idJurnal): array
    {
        if ($idJurnal === []) {
            return [];
        }

        $hasil = [];

        foreach (Jurnal::query()->whereIn('IdJurnalDibalik', $idJurnal)->pluck('IdJurnalDibalik') as $id) {
            $hasil[(int) $id] = true;
        }

        return $hasil;
    }

    private static function CekTanggal(string $nilai): bool
    {
        return preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $nilai) === 1;
    }
}
