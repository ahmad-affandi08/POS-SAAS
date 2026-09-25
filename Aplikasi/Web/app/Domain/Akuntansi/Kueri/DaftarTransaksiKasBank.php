<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Kueri;

use App\Domain\Akuntansi\Enum\JenisTransaksiKasBank;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\TransaksiKasBank;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Daftar transaksi kas & bank (F-13a, tipe FE `BarisTransaksiKasBank`) untuk `TabelData` (D-16): cari nomor &
 * keterangan; saring rentang tanggal, jenis, outlet; urut tanggal/nomor/jumlah. Pengguna yang aksesnya dibatasi ke
 * outlet tertentu hanya melihat transaksi di outlet aksesnya (transaksi tingkat usaha disembunyikan).
 */
final class DaftarTransaksiKasBank
{
    public const KOLOM_URUT = ['Tanggal', 'Nomor', 'Jumlah'];

    public const KOLOM_SARING = ['Tanggal', 'Jenis', 'Outlet'];

    public const URUT_BAWAAN = '-Tanggal';

    public function __construct(private readonly PetaUuidOutlet $outlet) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan, ?array $idOutletBoleh): array
    {
        $kata = $permintaan->cari;
        $tanggal = $permintaan->AmbilRentangTanggal('Tanggal');
        $jenis = $permintaan->AmbilDaftar('Jenis', array_map(fn (JenisTransaksiKasBank $j): string => $j->value, JenisTransaksiKasBank::cases()));
        $uuidOutlet = $permintaan->AmbilDaftar('Outlet');
        $idOutlet = $uuidOutlet === [] ? null : array_values($this->outlet->AmbilIdDariUuid($uuidOutlet));

        $kueri = TransaksiKasBank::query()
            ->when($kata !== '', function (Builder $k) use ($kata): void {
                $pola = PenerapKueriTabel::PolaCari($kata);
                $k->where(fn (Builder $s) => $s->where('Nomor', 'like', $pola)->orWhere('Keterangan', 'like', $pola));
            })
            ->when($tanggal['Dari'] !== null, fn (Builder $k) => $k->where('Tanggal', '>=', $tanggal['Dari']))
            ->when($tanggal['Sampai'] !== null, fn (Builder $k) => $k->where('Tanggal', '<=', $tanggal['Sampai']))
            ->when($jenis !== [], fn (Builder $k) => $k->whereIn('Jenis', $jenis))
            ->when($idOutlet !== null, fn (Builder $k) => $k->whereIn('IdOutlet', $idOutlet ?? []));
        self::BatasiOutlet($kueri, $idOutletBoleh);

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, ['Tanggal' => 'Tanggal', 'Nomor' => 'Nomor', 'Jumlah' => 'Jumlah'], fn (Collection $transaksi): array => $this->Petakan($transaksi));
    }

    /**
     * Transaksi tingkat usaha (tanpa outlet) atau di luar outlet akses disembunyikan bagi pengguna berbatas outlet.
     *
     * @param  Builder<TransaksiKasBank>  $kueri
     * @param  list<int>|null  $idOutletBoleh
     */
    public static function BatasiOutlet(Builder $kueri, ?array $idOutletBoleh): void
    {
        if ($idOutletBoleh !== null) {
            $kueri->whereIn('IdOutlet', $idOutletBoleh === [] ? [0] : $idOutletBoleh);
        }
    }

    /**
     * @param  Collection<int, TransaksiKasBank>  $transaksi
     * @return list<array<string, mixed>>
     */
    private function Petakan(Collection $transaksi): array
    {
        $idAkun = array_values(array_unique([...$transaksi->pluck('IdAkunSumber')->all(), ...$transaksi->pluck('IdAkunTujuan')->all()]));
        $akun = Akun::query()->whereKey($idAkun)->get(['Id', 'Kode', 'Nama'])->keyBy('Id');
        $idOutlet = array_values(array_unique(array_filter($transaksi->pluck('IdOutlet')->all(), 'is_int')));
        $namaOutlet = array_column($idOutlet === [] ? [] : $this->outlet->AmbilRingkas($idOutlet), 'Nama', 'Id');
        $dibalik = array_flip(array_map('intval', TransaksiKasBank::query()->whereIn('IdTransaksiDibalik', $transaksi->pluck('Id')->all())->pluck('IdTransaksiDibalik')->all()));

        return array_values($transaksi->map(fn (TransaksiKasBank $t): array => [
            ...self::PetakanBaris($t, $akun->get($t->IdAkunSumber), $akun->get($t->IdAkunTujuan), $t->IdOutlet === null ? null : ($namaOutlet[$t->IdOutlet] ?? null)),
            'Dibalik' => isset($dibalik[$t->Id]),
        ])->all());
    }

    /**
     * @return array<string, mixed>
     */
    public static function PetakanBaris(TransaksiKasBank $t, ?Akun $sumber, ?Akun $tujuan, ?string $namaOutlet): array
    {
        return [
            'Uuid' => $t->Uuid,
            'Nomor' => $t->Nomor,
            'Tanggal' => $t->Tanggal->toDateString(),
            'Jenis' => $t->Jenis->value,
            'LabelJenis' => $t->Jenis->AmbilLabel(),
            'NamaOutlet' => $namaOutlet,
            'AkunSumber' => $sumber?->AmbilLabel() ?? '',
            'AkunTujuan' => $tujuan?->AmbilLabel() ?? '',
            'Jumlah' => $t->Jumlah,
            'Keterangan' => $t->Keterangan,
            'Pembalik' => $t->IdTransaksiDibalik !== null,
            'AdaLampiran' => $t->PathLampiran !== null,
        ];
    }
}
