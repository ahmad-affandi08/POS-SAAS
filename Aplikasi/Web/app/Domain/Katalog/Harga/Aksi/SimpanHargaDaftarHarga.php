<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Harga\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Harga\Data\DataBarisHarga;
use App\Domain\Katalog\Harga\Data\HasilSimpanHarga;
use App\Domain\Katalog\Harga\Enum\SumberPerubahanHarga;
use App\Domain\Katalog\Harga\Layanan\PenyelarasBarisHarga;
use App\Domain\Katalog\Harga\Model\DaftarHarga;
use App\Domain\Katalog\Model\ProdukSatuan;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan harga satuan produk di satu daftar harga (lapis 3), termasuk tingkat jumlah di dalam daftar.
 * - `$perSatuan` = `[IdProdukSatuan => list<DataBarisHarga>]`: baris daftar untuk satuan yang disebut diganti;
 *   daftar kosong = satuan itu dikeluarkan dari daftar. Satuan lain tidak disentuh.
 * - Validasi sama dengan harga dasar, tanpa kewajiban `JumlahMinimum = 1` (daftar boleh hanya berisi 12+).
 *   Satuan harus milik tenant dan produknya belum dihapus (`SatuanTidakDikenal`).
 * - Setiap beda = satu `RiwayatHarga` (dengan `IdDaftarHarga`, BR-03.3). Audit `daftar-harga.harga.ubah`, kecuali
 *   sumber `Impor`.
 *
 * Urutan kunci: Tenant → baris `ProdukHarga` daftar ini untuk satuan yang disebut (FOR UPDATE).
 */
final class SimpanHargaDaftarHarga
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
        private readonly PenyelarasBarisHarga $penyelaras,
    ) {}

    /**
     * @param  array<int, list<DataBarisHarga>>  $perSatuan
     */
    public function Jalankan(DaftarHarga $daftar, array $perSatuan, SumberPerubahanHarga $sumber): HasilSimpanHarga
    {
        $idTenant = $this->konteks->Wajib();

        if ($perSatuan === []) {
            return new HasilSimpanHarga(0, 0, 0);
        }

        return DB::transaction(function () use ($idTenant, $daftar, $perSatuan, $sumber): HasilSimpanHarga {
            $this->penguncian->Kunci($idTenant);
            $satuan = ProdukSatuan::query()
                ->whereIn('Id', array_keys($perSatuan))
                ->whereHas('Produk')
                ->with('SatuanUnit')
                ->get()
                ->keyBy('Id');
            $satuanValid = $this->penyelaras->Validasi($perSatuan, $satuan, false);
            $hasil = $this->penyelaras->Selaraskan($perSatuan, $satuanValid, $daftar->Id, $sumber);

            if ($hasil->hasil->CekAdaPerubahan()) {
                // POS delta: daftar harga yang barisnya berubah ikut dikirim ulang.
                $daftar->touch();

                if ($sumber !== SumberPerubahanHarga::Impor) {
                    $this->audit->Catat('daftar-harga.harga.ubah', $daftar, ['Harga' => $hasil->nilaiLama], ['Harga' => $hasil->nilaiBaru, 'Sumber' => $sumber->value]);
                }
            }

            return $hasil->hasil;
        });
    }
}
