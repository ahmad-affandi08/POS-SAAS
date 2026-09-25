<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Pelanggan\Kueri\IdentitasPelanggan;
use App\Domain\Penjualan\Enum\StatusPesananPenjualan;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PesananPenjualan;
use App\Domain\Penjualan\Model\PesananPenjualanDetail;
use App\Domain\Penjualan\Model\PesananPenjualanPembayaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Pre-order back-office (F-12 bagian 2): daftar `TabelData` mode server (cari nomor, saring status & tanggal ambil,
 * urut tanggal ambil/nomor/uang muka) dan detail (baris, pembayaran DP, penjualan pengambilan). Pengguna terbatas outlet
 * hanya melihat pre-order outletnya.
 */
final class DaftarPesananPenjualan
{
    public const KOLOM_URUT = ['TanggalAmbil', 'Nomor', 'UangMuka'];

    public const KOLOM_SARING = ['Status', 'TanggalAmbil'];

    public const URUT_BAWAAN = 'TanggalAmbil';

    public function __construct(
        private readonly IdentitasPelanggan $pelanggan,
        private readonly PetaUuidOutlet $outlet,
    ) {}

    /**
     * @param  list<int>|null  $idOutletBoleh
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan, ?array $idOutletBoleh): array
    {
        $status = $permintaan->AmbilDaftar('Status', array_map(fn (StatusPesananPenjualan $s): string => $s->value, StatusPesananPenjualan::cases()));
        $tanggal = $permintaan->AmbilRentangTanggal('TanggalAmbil');
        $kueri = PesananPenjualan::query()
            ->when($idOutletBoleh !== null, fn (Builder $k) => $k->whereIn('IdOutlet', $idOutletBoleh === [] ? [0] : $idOutletBoleh))
            ->when($permintaan->cari !== '', function (Builder $k) use ($permintaan): void {
                $idPelanggan = $this->pelanggan->CariIdPos($permintaan->cari);
                $k->where(fn (Builder $s) => $s->where('Nomor', 'like', PenerapKueriTabel::PolaCari($permintaan->cari))
                    ->when($idPelanggan !== [], fn (Builder $a) => $a->orWhereIn('IdPelanggan', $idPelanggan)));
            })
            ->when($status !== [], fn (Builder $k) => $k->whereIn('Status', $status))
            ->when($tanggal['Dari'] !== null, fn (Builder $k) => $k->where('TanggalAmbil', '>=', $tanggal['Dari']))
            ->when($tanggal['Sampai'] !== null, fn (Builder $k) => $k->where('TanggalAmbil', '<=', $tanggal['Sampai']));

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, ['TanggalAmbil' => 'TanggalAmbil', 'Nomor' => 'Nomor', 'UangMuka' => 'UangMuka'], fn (Collection $daftar): array => $this->Petakan($daftar));
    }

    /**
     * @return array<string, mixed>
     */
    public function AmbilDetail(PesananPenjualan $pesanan): array
    {
        $penjualan = $pesanan->IdPenjualan === null ? null : Penjualan::query()->whereKey($pesanan->IdPenjualan)->first(['Uuid', 'Nomor', 'TotalAkhir']);

        return [
            ...$this->Petakan(collect([$pesanan]))[0],
            'Catatan' => $pesanan->Catatan,
            'DipesanPada' => $pesanan->DipesanPada->toIso8601ZuluString(),
            'SiapPada' => $pesanan->SiapPada?->toIso8601ZuluString(),
            'DiambilPada' => $pesanan->DiambilPada?->toIso8601ZuluString(),
            'DibatalkanPada' => $pesanan->DibatalkanPada?->toIso8601ZuluString(),
            'AlasanBatal' => $pesanan->AlasanBatal,
            'UangMukaTerpakai' => $pesanan->UangMukaTerpakai,
            'UangMukaDikembalikan' => $pesanan->UangMukaDikembalikan,
            'UangMukaHangus' => $pesanan->UangMukaHangus,
            'Penjualan' => $penjualan === null ? null : ['Uuid' => $penjualan->Uuid, 'Nomor' => $penjualan->Nomor, 'TotalAkhir' => $penjualan->TotalAkhir],
            'Baris' => array_values(PesananPenjualanDetail::query()->where('IdPesananPenjualan', $pesanan->Id)->orderBy('Id')->get()->map(fn (PesananPenjualanDetail $d): array => [
                'Uuid' => $d->Uuid,
                'NamaProduk' => $d->NamaProduk,
                'Jumlah' => $d->Jumlah,
                'HargaSatuan' => $d->HargaSatuan,
                'HargaPilihan' => $d->HargaPilihan,
                'Pilihan' => array_values(array_map(fn (array $p): string => $p['Nama'], $d->Pilihan ?? [])),
                'Catatan' => $d->Catatan,
            ])->all()),
            'Pembayaran' => array_values(PesananPenjualanPembayaran::query()->where('IdPesananPenjualan', $pesanan->Id)->orderBy('Id')->get()->map(fn (PesananPenjualanPembayaran $b): array => [
                'Uuid' => $b->Uuid,
                'Metode' => $b->JenisMetode->AmbilLabel(),
                'Jumlah' => $b->Jumlah,
                'Referensi' => $b->Referensi,
            ])->all()),
        ];
    }

    /**
     * @param  Collection<int, PesananPenjualan>  $daftar
     * @return list<array<string, mixed>>
     */
    private function Petakan(Collection $daftar): array
    {
        $pelanggan = $this->pelanggan->AmbilUntukPos(array_values($daftar->pluck('IdPelanggan')->all()));
        $outlet = array_column($this->outlet->AmbilRingkas(array_values(array_unique($daftar->pluck('IdOutlet')->all()))), 'Nama', 'Id');

        return array_values($daftar->map(fn (PesananPenjualan $p): array => [
            'Uuid' => $p->Uuid,
            'Nomor' => $p->Nomor,
            'Status' => $p->Status->value,
            'LabelStatus' => $p->Status->AmbilLabel(),
            'TanggalAmbil' => $p->TanggalAmbil->toDateString(),
            'TanggalPesan' => $p->TanggalBisnis->toDateString(),
            'Pelanggan' => $pelanggan[$p->IdPelanggan]['Nama'] ?? '-',
            'NoHpPelanggan' => $pelanggan[$p->IdPelanggan]['NoHp'] ?? null,
            'Outlet' => $outlet[$p->IdOutlet] ?? '-',
            'TotalPesanan' => $p->TotalPesanan,
            'UangMuka' => $p->UangMuka,
            'SisaUangMuka' => $p->AmbilSisaUangMuka()->KeString(),
        ])->all());
    }
}
