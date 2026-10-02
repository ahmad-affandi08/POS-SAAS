<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Penjualan\Data\DataSaringLaporanPenjualan;
use Carbon\CarbonImmutable;

/**
 * K-24: ringkasan akhir hari satu outlet untuk aplikasi kasir, dihitung langsung dari dokumen (bukan tabel turunan
 * yang diperbarui antrean) sehingga mencakup semua perangkat di outlet: jumlah transaksi/void/retur, kotor, diskon,
 * retur, bersih, pajak, uang per metode bayar, dan per kasir. HPP & laba sengaja tidak dikirim ke perangkat kasir.
 */
final class RingkasanAkhirHariPos
{
    public function __construct(
        private readonly AgregatPenjualan $agregat,
        private readonly AnggotaOutlet $anggota,
    ) {}

    /**
     * @return array{Tanggal: string, JumlahTransaksi: int, JumlahVoid: int, JumlahRetur: int, Kotor: string, Diskon: string, Retur: string, Bersih: string, Pajak: string, PerMetodeBayar: list<array{Jenis: string, Nama: string, Jumlah: string}>, PerKasir: list<array{Nama: string, JumlahTransaksi: int, Bersih: string}>}
     */
    public function Ambil(int $idOutlet, string $tanggal): array
    {
        $hari = CarbonImmutable::parse($tanggal);
        $saring = new DataSaringLaporanPenjualan($hari, $hari, [$idOutlet]);
        $harian = $this->agregat->Harian($saring)[0] ?? null;
        $total = $harian['Agregat'] ?? $this->agregat->Total($saring);
        $perKasir = $this->agregat->Agregasi($saring, ['Kasir']);
        $nama = $this->anggota->AmbilNama(array_values(array_map(fn (array $b): int => (int) $b['Kunci'][0], $perKasir)));
        $baris = array_values(array_map(fn (array $b): array => [
            'Nama' => $nama[(int) $b['Kunci'][0]]['Nama'] ?? 'Pengguna',
            'JumlahTransaksi' => $b['Agregat']->jumlahTransaksi,
            'Bersih' => $b['Agregat']->Bersih()->KeString(),
        ], $perKasir));
        usort($baris, fn (array $a, array $b): int => $b['JumlahTransaksi'] <=> $a['JumlahTransaksi']);

        return [
            'Tanggal' => $hari->toDateString(),
            'JumlahTransaksi' => $total->jumlahTransaksi,
            'JumlahVoid' => $harian['JumlahVoid'] ?? 0,
            'JumlahRetur' => $total->jumlahRetur,
            'Kotor' => $total->kotor->KeString(),
            'Diskon' => $total->diskon->KeString(),
            'Retur' => $total->retur->KeString(),
            'Bersih' => $total->Bersih()->KeString(),
            'Pajak' => $total->pajak->KeString(),
            'PerMetodeBayar' => array_values(array_map(fn (array $m): array => [
                'Jenis' => $m['Jenis'],
                'Nama' => $m['Nama'],
                'Jumlah' => $m['Jumlah'],
            ], $harian['PerMetodeBayar'] ?? [])),
            'PerKasir' => $baris,
        ];
    }
}
