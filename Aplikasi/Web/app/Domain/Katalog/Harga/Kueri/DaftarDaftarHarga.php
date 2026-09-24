<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Kueri;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Harga\Layanan\WaktuLokalDaftarHarga;
use App\Domain\Katalog\Harga\Model\DaftarHarga;
use App\Domain\Katalog\Model\ProdukHarga;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use App\Domain\Tenant\Kueri\ProfilTenant;

/**
 * Daftar harga tenant untuk halaman `Kelola/DaftarHarga/Daftar` (tipe FE `BarisDaftarHarga`, E.7): aktif dulu, lalu
 * prioritas tertinggi, lalu nama; 25 per halaman. Juga opsi outlet/kanal, zona waktu tenant, dan ringkasan kondisi
 * daftar untuk halaman harga produk.
 */
final class DaftarDaftarHarga
{
    public const PER_HALAMAN = 25;

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PetaUuidOutlet $petaOutlet,
        private readonly ProfilTenant $profilTenant,
    ) {}

    /**
     * @return array{Data: list<array<string, mixed>>, HalamanSaatIni: int, HalamanTerakhir: int, Total: int}
     */
    public function Ambil(int $halaman = 1): array
    {
        $hasil = DaftarHarga::query()
            ->orderByDesc('Aktif')
            ->orderByDesc('Prioritas')
            ->orderBy('Nama')
            ->orderBy('Id')
            ->paginate(self::PER_HALAMAN, ['*'], 'halaman', max(1, $halaman))
            ->withQueryString();
        /** @var list<DaftarHarga> $daftar */
        $daftar = $hasil->items();
        $namaOutlet = $this->AmbilNamaOutlet($daftar);
        $zona = $this->AmbilZonaWaktu();
        $jumlahProduk = ProdukHarga::query()
            ->whereIn('IdDaftarHarga', array_map(fn (DaftarHarga $d): int => $d->Id, $daftar))
            ->selectRaw('IdDaftarHarga, count(distinct IdProduk) as Jumlah')
            ->groupBy('IdDaftarHarga')
            ->pluck('Jumlah', 'IdDaftarHarga');

        return [
            'Data' => array_map(fn (DaftarHarga $d): array => [
                'Uuid' => $d->Uuid,
                'Nama' => $d->Nama,
                'NamaOutlet' => $d->IdOutlet === null ? null : array_values(array_filter(array_map(fn (int $id): ?string => $namaOutlet[$id] ?? null, $d->IdOutlet))),
                'Kanal' => $d->Kanal?->value,
                'LabelKanal' => $d->Kanal?->AmbilLabel(),
                'TierPelanggan' => $d->TierPelanggan,
                'MulaiPada' => $d->MulaiPada === null ? null : WaktuLokalDaftarHarga::KeTeks($d->MulaiPada, $zona),
                'SelesaiPada' => $d->SelesaiPada === null ? null : WaktuLokalDaftarHarga::KeTeks($d->SelesaiPada, $zona),
                'Prioritas' => $d->Prioritas,
                'Aktif' => $d->Aktif,
                'JumlahProduk' => (int) ($jumlahProduk->get($d->Id) ?? 0),
            ], $daftar),
            'HalamanSaatIni' => $hasil->currentPage(),
            'HalamanTerakhir' => $hasil->lastPage(),
            'Total' => $hasil->total(),
        ];
    }

    /**
     * Opsi outlet form daftar harga (`Pilihan`: Nilai = Uuid). Pelaku yang dibatasi outlet hanya melihat outletnya.
     *
     * @param  list<int>|null  $idOutletBoleh
     * @return list<array{Nilai: string, Label: string}>
     */
    public function AmbilOpsiOutlet(?array $idOutletBoleh): array
    {
        return array_map(
            fn (array $outlet): array => ['Nilai' => $outlet['Uuid'], 'Label' => $outlet['Nama']],
            $this->petaOutlet->AmbilRingkas($idOutletBoleh, true),
        );
    }

    /**
     * @return list<array{Nilai: string, Label: string}>
     */
    public static function AmbilOpsiKanal(): array
    {
        return array_map(fn (KanalPenjualan $kanal): array => ['Nilai' => $kanal->value, 'Label' => $kanal->AmbilLabel()], KanalPenjualan::cases());
    }

    public function AmbilZonaWaktu(): string
    {
        return $this->profilTenant->Ambil($this->konteks->Wajib())['ZonaWaktu'];
    }

    /**
     * Ringkasan kondisi daftar untuk ditampilkan, misal "Outlet Bandara · Online · Tier Grosir · Prioritas 10".
     *
     * @param  array<int, string>  $namaOutlet
     */
    public static function BuatRingkasan(DaftarHarga $daftar, array $namaOutlet, string $zonaWaktu): string
    {
        $bagian = [$daftar->IdOutlet === null
            ? 'Semua outlet'
            : implode(', ', array_filter(array_map(fn (int $id): ?string => $namaOutlet[$id] ?? null, $daftar->IdOutlet)))];
        $bagian[] = $daftar->Kanal?->AmbilLabel() ?? 'Semua kanal';

        if ($daftar->TierPelanggan !== null) {
            $bagian[] = "Tier {$daftar->TierPelanggan}";
        }

        if ($daftar->MulaiPada !== null || $daftar->SelesaiPada !== null) {
            $mulai = $daftar->MulaiPada === null ? '…' : str_replace('T', ' ', WaktuLokalDaftarHarga::KeTeks($daftar->MulaiPada, $zonaWaktu));
            $selesai = $daftar->SelesaiPada === null ? '…' : str_replace('T', ' ', WaktuLokalDaftarHarga::KeTeks($daftar->SelesaiPada, $zonaWaktu));
            $bagian[] = "{$mulai} – {$selesai}";
        }

        $bagian[] = "Prioritas {$daftar->Prioritas}";

        return implode(' · ', $bagian);
    }

    /**
     * @param  list<DaftarHarga>  $daftar
     * @return array<int, string>
     */
    public function AmbilNamaOutlet(array $daftar): array
    {
        $id = [];

        foreach ($daftar as $item) {
            $id = [...$id, ...($item->IdOutlet ?? [])];
        }

        $nama = [];

        foreach ($this->petaOutlet->AmbilRingkas(array_values(array_unique($id))) as $outlet) {
            $nama[$outlet['Id']] = $outlet['Nama'];
        }

        return $id === [] ? [] : $nama;
    }
}
