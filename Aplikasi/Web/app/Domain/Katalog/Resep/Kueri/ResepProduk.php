<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Resep\Kueri;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Model\Produk;
use App\Domain\Katalog\Model\Satuan;
use App\Domain\Katalog\Resep\Model\Resep;
use App\Domain\Katalog\Resep\Model\ResepDetail;
use App\Domain\Organisasi\Kueri\DaftarAnggota;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Isi halaman resep produk (tipe FE `PropsResepProduk` tanpa `Kepala`, `Hpp`, `Izin`; F-03 E.9): versi yang
 * diminta (bawaan terbaru; versi lama hanya-baca), nomor versi terbaru, dan daftar versi (terbaru dulu).
 */
final class ResepProduk
{
    public function __construct(private readonly DaftarAnggota $anggota, private readonly KonteksTenant $konteks) {}

    /**
     * @return array{Resep: array{Versi: int, JumlahHasil: string, SimbolSatuanHasil: string, Catatan: string|null, DibuatPada: string, NamaPembuat: string|null, Bahan: list<array{UuidProdukBahan: string, NamaBahan: string, Sku: string|null, Jumlah: string, UuidSatuan: string, SimbolSatuan: string, JumlahDasar: string, SimbolSatuanDasar: string, PersenSusut: string}>}|null, VersiTerbaru: int|null, DaftarVersi: list<array{Versi: int, DibuatPada: string, NamaPembuat: string|null}>}
     *
     * @throws ModelNotFoundException versi yang diminta tidak ada
     */
    public function Ambil(Produk $produk, ?int $versi = null): array
    {
        $daftar = Resep::query()->where('IdProduk', $produk->Id)->orderByDesc('Versi')->get(['Id', 'Versi', 'JumlahHasil', 'Catatan', 'DibuatOleh', 'DibuatPada']);
        $nama = $this->anggota->AmbilNamaPengguna($this->konteks->Wajib(), array_values(array_unique(array_filter($daftar->pluck('DibuatOleh')->all(), 'is_int'))));
        $dipilih = $versi === null ? $daftar->first() : $daftar->firstWhere('Versi', $versi);

        if ($versi !== null && $dipilih === null) {
            throw (new ModelNotFoundException)->setModel(Resep::class);
        }

        return [
            'Resep' => $dipilih === null ? null : $this->SusunResep($produk, $dipilih, $nama),
            'VersiTerbaru' => $daftar->first()?->Versi,
            'DaftarVersi' => array_values($daftar->map(fn (Resep $resep): array => [
                'Versi' => $resep->Versi,
                'DibuatPada' => (string) $resep->DibuatPada?->toIso8601String(),
                'NamaPembuat' => $resep->DibuatOleh === null ? null : ($nama[$resep->DibuatOleh] ?? null),
            ])->all()),
        ];
    }

    /**
     * @param  array<int, string>  $nama
     * @return array{Versi: int, JumlahHasil: string, SimbolSatuanHasil: string, Catatan: string|null, DibuatPada: string, NamaPembuat: string|null, Bahan: list<array{UuidProdukBahan: string, NamaBahan: string, Sku: string|null, Jumlah: string, UuidSatuan: string, SimbolSatuan: string, JumlahDasar: string, SimbolSatuanDasar: string, PersenSusut: string}>}
     */
    private function SusunResep(Produk $produk, Resep $resep, array $nama): array
    {
        $detail = ResepDetail::query()->with(['ProdukBahan:Id,Uuid,Nama,Sku,IdSatuanDasar', 'Satuan:Id,Uuid,Simbol'])->where('IdResep', $resep->Id)->orderBy('Urutan')->get();
        $idSatuanDasar = $detail->map(fn (ResepDetail $baris): int => $baris->ProdukBahan->IdSatuanDasar)->push($produk->IdSatuanDasar)->unique()->values()->all();
        $simbol = Satuan::query()->whereKey($idSatuanDasar)->pluck('Simbol', 'Id');

        return [
            'Versi' => $resep->Versi,
            'JumlahHasil' => $resep->JumlahHasil,
            'SimbolSatuanHasil' => (string) $simbol->get($produk->IdSatuanDasar, ''),
            'Catatan' => $resep->Catatan,
            'DibuatPada' => (string) $resep->DibuatPada?->toIso8601String(),
            'NamaPembuat' => $resep->DibuatOleh === null ? null : ($nama[$resep->DibuatOleh] ?? null),
            'Bahan' => array_values($detail->map(fn (ResepDetail $baris): array => [
                'UuidProdukBahan' => $baris->ProdukBahan->Uuid,
                'NamaBahan' => $baris->ProdukBahan->Nama,
                'Sku' => $baris->ProdukBahan->Sku,
                'Jumlah' => $baris->Jumlah,
                'UuidSatuan' => $baris->Satuan->Uuid,
                'SimbolSatuan' => $baris->Satuan->Simbol,
                'JumlahDasar' => $baris->JumlahDasar,
                'SimbolSatuanDasar' => (string) $simbol->get($baris->ProdukBahan->IdSatuanDasar, ''),
                'PersenSusut' => $baris->PersenSusut,
            ])->all()),
        ];
    }
}
