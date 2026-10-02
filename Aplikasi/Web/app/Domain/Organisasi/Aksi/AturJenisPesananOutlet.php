<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Enum\JenisPesanan;
use App\Domain\Organisasi\Model\Outlet;
use Illuminate\Support\Facades\DB;

/**
 * Jenis pesanan kasir outlet (§9.1–§9.2, v3.51): daftar yang ditawarkan di layar Jual dan bawaannya untuk transaksi
 * baru. Daftar kosong = kasir tanpa pilihan (semua penjualan Bawa pulang); `otomatis` = kembali mengikuti mode kasir
 * outlet. Bawaan wajib salah satu dari daftar. Audit `outlet.jenis-pesanan.ubah`; perangkat menerima perubahan saat
 * memuat ulang data awal.
 */
final class AturJenisPesananOutlet
{
    public function __construct(private readonly PencatatAudit $audit) {}

    /**
     * @param  list<JenisPesanan>  $daftar
     */
    public function Jalankan(Outlet $outlet, bool $otomatis, array $daftar, ?JenisPesanan $bawaan): Outlet
    {
        return DB::transaction(function () use ($outlet, $otomatis, $daftar, $bawaan): Outlet {
            $outlet = Outlet::query()->lockForUpdate()->findOrFail($outlet->Id);
            // Urutan simpanan selalu urutan enum (Makan di tempat, Bawa pulang, Antar), apa pun urutan kiriman.
            $nilai = array_values(array_map(
                fn (JenisPesanan $j): string => $j->value,
                array_filter(JenisPesanan::cases(), fn (JenisPesanan $j): bool => in_array($j, $daftar, true)),
            ));

            if (! $otomatis && $nilai !== [] && ($bawaan === null || ! in_array($bawaan->value, $nilai, true))) {
                throw new PelanggaranAturanBisnis('BawaanTidakDipilih', 'Pilih jenis pesanan bawaan dari jenis yang ditawarkan.', 'JenisPesananBawaan');
            }

            $lama = $outlet->PengaturanKasir;
            $baru = $otomatis ? null : ['JenisPesanan' => $nilai, 'JenisPesananBawaan' => $nilai === [] ? null : $bawaan?->value];

            if ($lama !== $baru) {
                $outlet->forceFill(['PengaturanKasir' => $baru])->save();
                $this->audit->Catat('outlet.jenis-pesanan.ubah', $outlet, nilaiLama: ['PengaturanKasir' => $lama], nilaiBaru: ['PengaturanKasir' => $baru]);
            }

            return $outlet;
        });
    }
}
