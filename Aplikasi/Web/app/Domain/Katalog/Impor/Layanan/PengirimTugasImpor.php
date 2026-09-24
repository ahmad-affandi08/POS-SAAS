<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Layanan;

use App\Domain\Katalog\Impor\Enum\StatusImporProduk;
use App\Domain\Katalog\Impor\Model\ImporProduk;
use App\Domain\Katalog\Impor\Tugas\TerapkanImporProdukTugas;
use App\Domain\Katalog\Impor\Tugas\ValidasiImporProdukTugas;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pengiriman tugas impor (F-03 BR-03.6): berkas kecil (≤ `katalog.Impor.BatasBarisSinkron` baris) langsung
 * diproses di request; berkas besar masuk antrean dan diproses worker scheduler per potongan. Dipanggil setelah
 * transaksi Aksi selesai.
 */
final class PengirimTugasImpor
{
    public static function KirimValidasi(ImporProduk $impor): void
    {
        self::Kirim(new ValidasiImporProdukTugas($impor->IdTenant, $impor->IdPengguna, $impor->Id), $impor->JumlahBaris);
    }

    public static function KirimPenerapan(ImporProduk $impor): void
    {
        self::Kirim(new TerapkanImporProdukTugas($impor->IdTenant, $impor->IdPengguna, $impor->Id), $impor->JumlahValid);
    }

    /** Tugas yang anggaran waktunya habis mengirim dirinya lagi ke antrean. */
    public static function KirimLanjutan(ShouldQueue $tugas): void
    {
        dispatch($tugas);
    }

    public static function Gagalkan(int $idImporProduk, string $pesan): void
    {
        DB::transaction(function () use ($idImporProduk, $pesan): void {
            $impor = ImporProduk::query()->whereKey($idImporProduk)->lockForUpdate()->first();

            if ($impor !== null && $impor->Status->BisaBerubahKe(StatusImporProduk::Gagal)) {
                $impor->UbahStatus(StatusImporProduk::Gagal);
                $impor->PesanGalat = mb_substr($pesan, 0, 500);
                $impor->save();
            }
        });
    }

    private static function Kirim(ShouldQueue $tugas, int $jumlahBaris): void
    {
        if ($jumlahBaris > (int) config('katalog.Impor.BatasBarisSinkron', 300)) {
            dispatch($tugas);

            return;
        }

        try {
            Bus::dispatchSync($tugas);
        } catch (Throwable $galat) {
            // Tugas sudah menandai impor Gagal lewat failed(); halaman detail menampilkan pesannya.
            report($galat);
        }
    }
}
