<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Aksi;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Laporan\Model\RingkasanPenjualanHarian;
use App\Domain\Penjualan\Data\DataSaringLaporanPenjualan;
use App\Domain\Penjualan\Kueri\AgregatPenjualan;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * F-14a: menghitung ulang penuh baris `RingkasanPenjualanHarian` tenant aktif untuk satu tanggal bisnis (semua outlet,
 * atau satu outlet) dari dokumen sumber lewat kueri publik domain Penjualan (`AgregatPenjualan::Harian`). Idempoten:
 * baris yang ada ditimpa (upsert pada kunci unik tenant/outlet/tanggal), baris outlet yang tidak lagi punya dokumen
 * dihapus, sehingga hasilnya selalu sama dengan Σ dokumen berapa kali pun dijalankan.
 */
final class BangunUlangRingkasanPenjualanHarian
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly AgregatPenjualan $agregat,
    ) {}

    /**
     * @return int jumlah baris ringkasan yang ditulis
     */
    public function Jalankan(CarbonImmutable $tanggal, ?int $idOutlet = null): int
    {
        $idTenant = $this->konteks->Wajib();
        $tanggal = $tanggal->startOfDay();
        $saring = new DataSaringLaporanPenjualan($tanggal, $tanggal, $idOutlet === null ? null : [$idOutlet]);

        return DB::transaction(function () use ($idTenant, $tanggal, $idOutlet, $saring): int {
            $sekarang = CarbonImmutable::now();
            $baris = [];

            foreach ($this->agregat->Harian($saring) as $h) {
                $a = $h['Agregat'];
                $baris[] = [
                    'IdTenant' => $idTenant,
                    'IdOutlet' => $h['IdOutlet'],
                    'TanggalBisnis' => $h['TanggalBisnis'],
                    'Kotor' => $a->kotor->KeString(),
                    'Diskon' => $a->diskon->KeString(),
                    'Retur' => $a->retur->KeString(),
                    'Bersih' => $a->Bersih()->KeString(),
                    'Pajak' => $a->pajak->KeString(),
                    'BiayaLayanan' => $a->biayaLayanan->KeString(),
                    'Hpp' => $a->hpp->KeString(),
                    'JumlahTransaksi' => $a->jumlahTransaksi,
                    'JumlahRetur' => $a->jumlahRetur,
                    'JumlahVoid' => $h['JumlahVoid'],
                    'PerMetodeBayar' => json_encode($h['PerMetodeBayar'], JSON_THROW_ON_ERROR),
                    'PerKanal' => json_encode($h['PerKanal'], JSON_THROW_ON_ERROR),
                    'DihitungPada' => $sekarang,
                    'DibuatPada' => $sekarang,
                    'DiubahPada' => $sekarang,
                ];
            }

            RingkasanPenjualanHarian::query()
                ->where('TanggalBisnis', $tanggal->toDateString())
                ->when($idOutlet !== null, fn (Builder $k) => $k->where('IdOutlet', $idOutlet))
                ->whereNotIn('IdOutlet', array_column($baris, 'IdOutlet'))
                ->delete();

            if ($baris !== []) {
                RingkasanPenjualanHarian::query()->upsert($baris, ['IdTenant', 'IdOutlet', 'TanggalBisnis'], [
                    'Kotor', 'Diskon', 'Retur', 'Bersih', 'Pajak', 'BiayaLayanan', 'Hpp', 'JumlahTransaksi', 'JumlahRetur', 'JumlahVoid',
                    'PerMetodeBayar', 'PerKanal', 'DihitungPada', 'DiubahPada',
                ]);
            }

            return count($baris);
        });
    }
}
