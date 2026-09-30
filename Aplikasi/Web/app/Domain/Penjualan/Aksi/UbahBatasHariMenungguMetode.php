<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Penjualan\Kueri\PembayaranBelumDicairkan;
use App\Domain\Penjualan\Model\MetodePembayaran;
use Illuminate\Support\Facades\DB;

/**
 * F-08 (BR-08.4): tenant mengatur berapa hari wajar menunggu pencairan untuk satu metode, karena kesepakatan tiap
 * platform berbeda (mis. ojol yang menyetor dua mingguan). Hanya metode yang uangnya lewat akun kliring; `null` mengembalikan
 * bawaan jenisnya. Angka ini hanya menentukan kapan butir pengingat Kotak Tindakan muncul: tidak memengaruhi jurnal.
 */
final class UbahBatasHariMenungguMetode
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(MetodePembayaran $metode, ?int $hari): MetodePembayaran
    {
        if (PembayaranBelumDicairkan::AmbilBatasHari($metode) === null) {
            throw new PelanggaranAturanBisnis('MetodeTanpaPencairan', "{$metode->Nama} tidak lewat pencairan, jadi tidak punya batas hari menunggu.", 'BatasHariMenunggu');
        }

        if ($hari !== null && ($hari < 1 || $hari > PembayaranBelumDicairkan::BATAS_HARI_MAKSIMAL)) {
            throw new PelanggaranAturanBisnis('BatasHariTidakSah', 'Batas hari menunggu 1 sampai '.PembayaranBelumDicairkan::BATAS_HARI_MAKSIMAL.' hari, atau kosongkan untuk memakai bawaan.', 'BatasHariMenunggu');
        }

        return DB::transaction(function () use ($metode, $hari): MetodePembayaran {
            $metode = MetodePembayaran::query()->lockForUpdate()->findOrFail($metode->Id);
            $lama = $metode->BatasHariMenunggu;

            if ($lama === $hari) {
                return $metode;
            }

            $metode->BatasHariMenunggu = $hari;
            $metode->save();
            $this->audit->Catat('metode-pembayaran.batas-hari-menunggu', $metode, nilaiLama: ['BatasHariMenunggu' => $lama], nilaiBaru: ['BatasHariMenunggu' => $hari]);

            return $metode;
        });
    }
}
