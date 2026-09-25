<?php

declare(strict_types=1);

namespace App\Domain\Promo\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Promo\Enum\StatusPemakaianVoucher;
use App\Domain\Promo\Enum\StatusVoucher;
use App\Domain\Promo\Model\Promo;
use App\Domain\Promo\Model\Voucher;
use App\Domain\Promo\Model\VoucherPemakaian;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Voucher sebuah promo (F-16c bagian 2) untuk `TabelData` mode server (cari kode, saring status, urut kode/pemakaian/
 * tanggal dibuat) dan ekspor CSV (semua baris, urut kode). `Dipesan` = pesanan kasir yang masih berlaku.
 */
final class DaftarVoucher
{
    public const KOLOM_URUT = ['Kode', 'JumlahDipakai', 'DibuatPada'];

    public const KOLOM_SARING = ['Status'];

    public const URUT_BAWAAN = '-DibuatPada';

    /**
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(Promo $promo, DataPermintaanTabel $permintaan): array
    {
        $kata = $permintaan->cari;
        $status = $permintaan->AmbilDaftar('Status', array_map(fn (StatusVoucher $s): string => $s->value, StatusVoucher::cases()));
        $kueri = Voucher::query()
            ->where('IdPromo', $promo->Id)
            ->when($kata !== '', fn (Builder $k) => $k->where('Kode', 'like', PenerapKueriTabel::PolaCari(strtoupper($kata))))
            ->when($status !== [], fn (Builder $k) => $k->whereIn('Status', $status));

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, ['Kode' => 'Kode', 'JumlahDipakai' => 'JumlahDipakai', 'DibuatPada' => 'Id'], fn (Collection $voucher): array => $this->Petakan($voucher));
    }

    /**
     * @return array{Total: int, Aktif: int, Dipakai: int}
     */
    public function AmbilRingkasan(Promo $promo): array
    {
        $kueri = Voucher::query()->where('IdPromo', $promo->Id);

        return [
            'Total' => (clone $kueri)->count(),
            'Aktif' => (clone $kueri)->where('Status', StatusVoucher::Aktif->value)->count(),
            'Dipakai' => (int) (clone $kueri)->sum('JumlahDipakai'),
        ];
    }

    /**
     * Baris CSV: kode, batas pakai, dipakai, kedaluwarsa (UTC ISO), status.
     *
     * @return iterable<list<string|int|null>>
     */
    public function AmbilUntukEkspor(Promo $promo): iterable
    {
        foreach (Voucher::query()->where('IdPromo', $promo->Id)->orderBy('Kode')->lazyById(1000, 'Id') as $v) {
            yield [$v->Kode, $v->MaksimalPakai, $v->JumlahDipakai, $v->KedaluwarsaPada?->toIso8601ZuluString(), $v->Status->value];
        }
    }

    /**
     * @param  Collection<int, Voucher>  $voucher
     * @return list<array<string, mixed>>
     */
    private function Petakan(Collection $voucher): array
    {
        $dipesan = VoucherPemakaian::query()
            ->whereIn('IdVoucher', $voucher->pluck('Id')->all())
            ->where('Status', StatusPemakaianVoucher::Dipesan->value)
            ->where('DipesanSampai', '>', CarbonImmutable::now())
            ->selectRaw('IdVoucher, COUNT(*) AS Jumlah')
            ->groupBy('IdVoucher')
            ->pluck('Jumlah', 'IdVoucher')
            ->all();

        return array_values($voucher->map(fn (Voucher $v): array => [
            'Uuid' => $v->Uuid,
            'Kode' => $v->Kode,
            'MaksimalPakai' => $v->MaksimalPakai,
            'JumlahDipakai' => $v->JumlahDipakai,
            'Dipesan' => (int) ($dipesan[$v->Id] ?? 0),
            'KedaluwarsaPada' => $v->KedaluwarsaPada?->toIso8601ZuluString(),
            'Status' => $v->Status->value,
            'DibuatPada' => $v->DibuatPada?->toIso8601ZuluString(),
        ])->all());
    }
}
