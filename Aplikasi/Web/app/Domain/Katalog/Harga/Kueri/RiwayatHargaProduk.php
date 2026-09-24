<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Harga\Enum\SumberPerubahanHarga;
use App\Domain\Katalog\Harga\Model\DaftarHarga;
use App\Domain\Katalog\Harga\Model\RiwayatHarga;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\Satuan;
use App\Domain\Organisasi\Kueri\DaftarAnggota;
use Illuminate\Support\Collection;

/**
 * Riwayat harga satu produk (BR-03.3, tipe FE `BarisRiwayatHarga`, `TabelData` D-16), bawaan terbaru dulu.
 */
final class RiwayatHargaProduk
{
    public const KOLOM_URUT = ['DibuatPada'];

    public const KOLOM_SARING = ['Sumber', 'Tanggal'];

    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly DaftarAnggota $anggota,
    ) {}

    /**
     * Riwayat untuk `TabelData` (D-16): saring sumber perubahan & rentang tanggal; urut waktu.
     *
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(Produk $produk, DataPermintaanTabel $permintaan): array
    {
        $sumber = $permintaan->AmbilDaftar('Sumber', array_map(fn (SumberPerubahanHarga $s): string => $s->value, SumberPerubahanHarga::cases()));
        $tanggal = $permintaan->AmbilRentangTanggal('Tanggal');
        $kueri = RiwayatHarga::query()
            ->where('IdProduk', $produk->Id)
            ->when($sumber !== [], fn ($k) => $k->whereIn('Sumber', $sumber))
            ->when($tanggal['Dari'] !== null, fn ($k) => $k->where('DibuatPada', '>=', $tanggal['Dari'].' 00:00:00'))
            ->when($tanggal['Sampai'] !== null, fn ($k) => $k->where('DibuatPada', '<=', $tanggal['Sampai'].' 23:59:59'));

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, ['DibuatPada' => 'DibuatPada'], fn (Collection $riwayat): array => $this->Petakan(array_values($riwayat->all())));
    }

    /**
     * @param  list<RiwayatHarga>  $baris
     * @return list<array<string, mixed>>
     */
    private function Petakan(array $baris): array
    {
        $namaSatuan = Satuan::query()->whereIn('Id', array_map(fn (RiwayatHarga $r): int => $r->IdSatuan, $baris))->pluck('Nama', 'Id');
        $namaDaftar = DaftarHarga::query()->whereIn('Id', array_filter(array_map(fn (RiwayatHarga $r): ?int => $r->IdDaftarHarga, $baris)))->pluck('Nama', 'Id');
        $idPengubah = array_values(array_unique(array_filter(array_map(fn (RiwayatHarga $r): ?int => $r->DiubahOleh, $baris))));
        $namaPengubah = $idPengubah === [] ? [] : $this->anggota->AmbilNamaPengguna($this->konteks->Wajib(), $idPengubah);

        return array_map(fn (RiwayatHarga $r): array => [
            'DibuatPada' => $r->DibuatPada?->utc()->toIso8601ZuluString() ?? '',
            'NamaSatuan' => (string) ($namaSatuan->get($r->IdSatuan) ?? ''),
            'NamaDaftarHarga' => $r->IdDaftarHarga === null ? null : (string) ($namaDaftar->get($r->IdDaftarHarga) ?? ''),
            'JumlahMinimum' => $r->JumlahMinimum,
            'HargaLama' => $r->HargaLama,
            'HargaBaru' => $r->HargaBaru,
            'NamaPengubah' => $r->DiubahOleh === null ? null : ($namaPengubah[$r->DiubahOleh] ?? null),
            'Sumber' => $r->Sumber->value,
            'LabelSumber' => $r->Sumber->AmbilLabel(),
        ], $baris);
    }
}
