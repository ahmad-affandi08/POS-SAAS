<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Layanan;

use App\Domain\Persediaan\Enum\StatusImporStokAwal;
use App\Domain\Persediaan\Impor\Tugas\TerapkanImporStokAwalTugas;
use App\Domain\Persediaan\Impor\Tugas\ValidasiImporStokAwalTugas;
use App\Domain\Persediaan\Model\ImporStokAwal;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pengiriman tugas impor stok awal (DesainF05a C.7): berkas kecil (≤ `persediaan.Impor.BatasBarisSinkron` baris)
 * langsung diproses di request; berkas besar masuk antrean dan diproses per potongan. Dipanggil setelah transaksi
 * Aksi selesai (setelah commit).
 */
final class PengirimTugasImporStokAwal
{
    public static function KirimValidasi(ImporStokAwal $impor): void
    {
        self::Kirim(new ValidasiImporStokAwalTugas($impor->IdTenant, $impor->IdPengguna, $impor->Id), $impor->JumlahBaris);
    }

    public static function KirimPenerapan(ImporStokAwal $impor): void
    {
        self::Kirim(new TerapkanImporStokAwalTugas($impor->IdTenant, $impor->IdPengguna, $impor->Id), $impor->JumlahValid);
    }

    /** Tugas yang anggaran waktunya habis mengirim dirinya lagi ke antrean. */
    public static function KirimLanjutan(ShouldQueue $tugas): void
    {
        dispatch($tugas);
    }

    public static function Gagalkan(int $idImporStokAwal, string $pesan): void
    {
        DB::transaction(function () use ($idImporStokAwal, $pesan): void {
            $impor = ImporStokAwal::query()->whereKey($idImporStokAwal)->lockForUpdate()->first();

            if ($impor !== null && $impor->Status->BisaBerubahKe(StatusImporStokAwal::Gagal)) {
                $impor->UbahStatus(StatusImporStokAwal::Gagal);
                $impor->PesanGalat = mb_substr($pesan, 0, 500);
                $impor->save();
            }
        });
    }

    private static function Kirim(ShouldQueue $tugas, int $jumlahBaris): void
    {
        if ($jumlahBaris > (int) config('persediaan.Impor.BatasBarisSinkron', 300)) {
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
