<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Pelanggan\Enum\StatusPelanggan;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\TierPelanggan;

/**
 * Tier pelanggan tenant (F-16b): daftar lengkap untuk halaman tier (≤ 10 aktif, mode lokal `TabelData`) dan opsi tier
 * aktif untuk formulir (dipakai juga halaman daftar harga domain Katalog).
 */
final class DaftarTierPelanggan
{
    /**
     * @return list<array{Uuid: string, Kode: string, Nama: string, MinimalBelanja: string, PengaliPoin: string, Urutan: int, Status: string, JumlahPelanggan: int}>
     */
    public function AmbilSemua(): array
    {
        $jumlah = Pelanggan::query()->whereNotNull('IdTier')->groupBy('IdTier')->selectRaw('IdTier, COUNT(*) AS Jumlah')->pluck('Jumlah', 'IdTier');

        return array_values(TierPelanggan::query()->orderBy('Urutan')->orderBy('MinimalBelanja')->orderBy('Id')->get()
            ->map(fn (TierPelanggan $t): array => [
                'Uuid' => $t->Uuid,
                'Kode' => $t->Kode,
                'Nama' => $t->Nama,
                'MinimalBelanja' => (string) $t->MinimalBelanja,
                'PengaliPoin' => (string) $t->PengaliPoin,
                'Urutan' => $t->Urutan,
                'Status' => $t->Status->value,
                'JumlahPelanggan' => (int) ($jumlah[$t->Id] ?? 0),
            ])->all());
    }

    /**
     * Tier aktif (plus [sertakanKode] bila sudah diarsipkan) sebagai opsi `{Nilai: Kode, Label: Nama}`.
     *
     * @return list<array{Nilai: string, Label: string, Uuid: string}>
     */
    public function AmbilOpsi(?string $sertakanKode = null): array
    {
        return array_values(TierPelanggan::query()
            ->where(fn ($k) => $k->where('Status', StatusPelanggan::Aktif->value)->when($sertakanKode !== null, fn ($atau) => $atau->orWhere('Kode', $sertakanKode)))
            ->orderBy('Urutan')
            ->orderBy('MinimalBelanja')
            ->get()
            ->map(fn (TierPelanggan $t): array => ['Nilai' => $t->Kode, 'Label' => "{$t->Nama} ({$t->Kode})", 'Uuid' => $t->Uuid])
            ->all());
    }

    /**
     * Peta Id tier → Kode & Nama (untuk daftar pelanggan & POS).
     *
     * @param  list<int>  $id
     * @return array<int, array{Kode: string, Nama: string}>
     */
    public function AmbilPeta(array $id): array
    {
        $hasil = [];

        foreach ($id === [] ? [] : TierPelanggan::query()->whereKey($id)->get(['Id', 'Kode', 'Nama']) as $t) {
            $hasil[$t->Id] = ['Kode' => $t->Kode, 'Nama' => $t->Nama];
        }

        return $hasil;
    }
}
