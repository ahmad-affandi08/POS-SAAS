<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use App\Domain\Penjualan\Enum\StatusPenjualan;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PenjualanPembayaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Daftar penjualan back-office (F-07b, izin `laporan.penjualan.lihat`) untuk `TabelData` mode server (D-16): cari
 * nomor atau nama kasir, saring outlet (hanya yang boleh diakses), status, kanal, rentang tanggal bisnis, dan hanya
 * yang perlu ditinjau; urut waktu transaksi, tanggal bisnis, total, atau nomor. Juga daftar penjualan satu shift
 * (detail shift F-06).
 */
final class DaftarPenjualan
{
    public const KOLOM_URUT = ['DibuatOfflinePada', 'TanggalBisnis', 'TotalAkhir', 'Nomor'];

    public const KOLOM_SARING = ['Outlet', 'Status', 'Kanal', 'TanggalBisnis', 'PerluTinjauan'];

    public const URUT_BAWAAN = '-DibuatOfflinePada';

    public function __construct(
        private readonly PetaUuidOutlet $outlet,
        private readonly AnggotaOutlet $anggota,
    ) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan, ?array $idOutletBoleh, int $idTenant): array
    {
        $uuidOutlet = $permintaan->AmbilDaftar('Outlet');
        $idOutlet = $uuidOutlet === [] ? null : array_values($this->outlet->AmbilIdDariUuid($uuidOutlet));
        $status = $permintaan->AmbilDaftar('Status', array_map(fn (StatusPenjualan $s): string => $s->value, StatusPenjualan::cases()));
        $kanal = $permintaan->AmbilDaftar('Kanal', array_map(fn (KanalPenjualan $k): string => $k->value, KanalPenjualan::cases()));
        $tanggal = $permintaan->AmbilRentangTanggal('TanggalBisnis');
        $cari = $permintaan->cari;
        $idKasir = $cari === '' ? [] : $this->anggota->CariIdDariNama($idTenant, $cari);

        $kueri = Penjualan::query()
            ->when($idOutletBoleh !== null, fn ($k) => $k->whereIn('IdOutlet', $idOutletBoleh ?? []))
            ->when($idOutlet !== null, fn ($k) => $k->whereIn('IdOutlet', $idOutlet ?? []))
            ->when($status !== [], fn ($k) => $k->whereIn('Status', $status))
            ->when($kanal !== [], fn ($k) => $k->whereIn('Kanal', $kanal))
            ->when($tanggal['Dari'] !== null, fn ($k) => $k->where('TanggalBisnis', '>=', $tanggal['Dari']))
            ->when($tanggal['Sampai'] !== null, fn ($k) => $k->where('TanggalBisnis', '<=', $tanggal['Sampai']))
            ->when($permintaan->AmbilBoolean('PerluTinjauan') === true, fn ($k) => $k->where('PerluTinjauan', true))
            ->when($cari !== '', fn (Builder $k) => $k->where(fn (Builder $dalam) => $dalam
                ->where('Nomor', 'like', PenerapKueriTabel::PolaCari($cari))
                ->when($idKasir !== [], fn (Builder $atau) => $atau->orWhereIn('IdPengguna', $idKasir))));

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, [
            'DibuatOfflinePada' => 'DibuatOfflinePada',
            'TanggalBisnis' => 'TanggalBisnis',
            'TotalAkhir' => 'TotalAkhir',
            'Nomor' => 'Nomor',
        ], fn (Collection $penjualan): array => $this->Petakan($penjualan));
    }

    /**
     * Penjualan satu shift urut waktu (detail shift). Ringkasan: jumlah transaksi & total penjualan.
     *
     * @return array{Daftar: list<array<string, mixed>>, JumlahTransaksi: int, TotalPenjualan: string}
     */
    public function AmbilUntukShift(int $idShift): array
    {
        $penjualan = Penjualan::query()->where('IdShift', $idShift)->orderBy('DibuatOfflinePada')->orderBy('Id')->get();
        $total = Penjualan::query()->where('IdShift', $idShift)->where('Status', StatusPenjualan::Lunas->value)->sum('TotalAkhir');

        return [
            'Daftar' => $this->Petakan($penjualan),
            'JumlahTransaksi' => $penjualan->count(),
            'TotalPenjualan' => Uang::Dari(is_numeric($total) ? (string) $total : '0')->KeString(),
        ];
    }

    /**
     * @param  Collection<int, Penjualan>  $penjualan
     * @return list<array<string, mixed>>
     */
    private function Petakan(Collection $penjualan): array
    {
        $outlet = array_column($this->outlet->AmbilRingkas(array_values(array_unique($penjualan->pluck('IdOutlet')->all()))), 'Nama', 'Id');
        $nama = $this->anggota->AmbilNama(array_values($penjualan->pluck('IdPengguna')->all()));
        $metode = PenjualanPembayaran::query()
            ->whereIn('IdPenjualan', $penjualan->pluck('Id')->all())
            ->orderBy('Urutan')
            ->get(['IdPenjualan', 'NamaMetode'])
            ->groupBy('IdPenjualan');

        return array_values($penjualan->map(fn (Penjualan $p): array => [
            'Uuid' => $p->Uuid,
            'Nomor' => $p->Nomor,
            'DibuatOfflinePada' => $p->DibuatOfflinePada->toIso8601String(),
            'TanggalBisnis' => $p->TanggalBisnis->toDateString(),
            'NamaOutlet' => $outlet[$p->IdOutlet] ?? '',
            'NamaKasir' => $nama[$p->IdPengguna]['Nama'] ?? '',
            'Kanal' => $p->Kanal->value,
            'LabelKanal' => $p->Kanal->AmbilLabel(),
            'TotalAkhir' => $p->TotalAkhir,
            'Metode' => array_values(array_unique($metode->get($p->Id, collect())->pluck('NamaMetode')->all())),
            'Status' => $p->Status->value,
            'LabelStatus' => $p->Status->AmbilLabel(),
            'PerluTinjauan' => $p->PerluTinjauan,
        ])->all());
    }
}
