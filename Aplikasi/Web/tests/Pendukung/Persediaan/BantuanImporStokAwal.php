<?php

declare(strict_types=1);

namespace Tests\Pendukung\Persediaan;

use App\Domain\Persediaan\Enum\StatusStokAwal;
use App\Domain\Persediaan\Enum\SumberStokAwal;
use App\Domain\Persediaan\Model\ImporStokAwal;
use App\Domain\Persediaan\Model\StokAwal;
use App\Domain\Persediaan\Model\StokAwalDetail;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Pendukung\Katalog\BantuanImpor;
use Tests\TestCase;

/**
 * Prasyarat test impor stok awal F-05a Tim E: judul templat, unggah + pemetaan lewat rute, dan stok awal Diposting
 * buatan langsung (untuk `StokAwalSudahAda`). Penulis berkas xlsx/csv & pembaca unduhan memakai `BantuanImpor` F-03.
 */
final class BantuanImporStokAwal
{
    /** Judul templat stok awal (sama dengan unduhan templat). */
    public const JUDUL = ['SKU', 'Nama Produk', 'Satuan', 'Lokasi Stok', 'Stok', 'Harga Modal', 'Nomor Batch', 'Kedaluwarsa', 'Nomor Seri'];

    /**
     * @param  list<list<string|int|null>>  $baris
     */
    public static function BuatCsv(array $baris, string $nama = 'stok awal.csv'): UploadedFile
    {
        return BantuanImpor::BuatCsv($baris, ',', false, 'UTF-8', $nama);
    }

    /**
     * @param  list<list<string|int|null>>  $baris
     */
    public static function BuatXlsx(array $baris, string $nama = 'stok awal.xlsx'): UploadedFile
    {
        return BantuanImpor::BuatXlsx($baris, $nama);
    }

    /** Unggah lewat rute; mengembalikan impor terbaru tenant aktif. */
    public static function Unggah(TestCase $tes, UploadedFile $berkas, ?string $uuidGudangBawaan = null): ImporStokAwal
    {
        $tes->post('/kelola/persediaan/stok-awal/impor', ['Berkas' => $berkas, 'UuidGudangBawaan' => $uuidGudangBawaan])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        return ImporStokAwal::query()->orderByDesc('Id')->firstOrFail();
    }

    /**
     * Simpan pemetaan (bawaan = pemetaan otomatis) + lokasi bawaan + tanggal.
     *
     * @param  array<string, int|null>|null  $pemetaan
     * @return TestResponse<Response>
     */
    public static function Petakan(TestCase $tes, ImporStokAwal $impor, ?string $uuidGudangBawaan = null, ?string $tanggal = null, ?array $pemetaan = null): TestResponse
    {
        return $tes->put("/kelola/persediaan/stok-awal/impor/{$impor->Uuid}/pemetaan", [
            'Pemetaan' => $pemetaan ?? $impor->Pemetaan,
            'UuidGudangBawaan' => $uuidGudangBawaan,
            'Tanggal' => $tanggal ?? now()->subDay()->toDateString(),
        ]);
    }

    /**
     * Dokumen stok awal Diposting tiruan (tanpa mutasi/jurnal) untuk pemeriksaan `StokAwalSudahAda`. Tenant konteks.
     */
    public static function BuatStokAwalDiposting(int $idGudang, int $idProduk, string $namaProduk): StokAwal
    {
        $stokAwal = StokAwal::query()->create([
            'Nomor' => 'SA/2026/09/'.str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
            'IdGudang' => $idGudang,
            'Tanggal' => now()->subMonth()->toDateString(),
            'Status' => StatusStokAwal::Diposting,
            'Sumber' => SumberStokAwal::Manual,
            'JumlahBaris' => 1,
            'TotalNilai' => '100000.00',
            'DipostingPada' => now(),
        ]);

        StokAwalDetail::query()->create([
            'IdStokAwal' => $stokAwal->Id,
            'Urutan' => 1,
            'IdProduk' => $idProduk,
            'NamaProduk' => $namaProduk,
            'Jumlah' => '10.0000',
            'HppSatuan' => '10000.000000',
            'Nilai' => '100000.00',
        ]);

        return $stokAwal;
    }
}
