<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Grosir;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Laporan\Layanan\PenulisCsvLaporan;
use App\Domain\Penjualan\Kueri\DaftarKunjunganSales;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kunjungan salesman (Modul Salesman bagian 1, §9.7, Grosir › Kunjungan, `/kelola/grosir/kunjungan`): daftar
 * kunjungan dari aplikasi salesman (hanya baca; catatan lapangan tidak diubah dari back-office) dan ekspor CSV dengan
 * saringan yang sama. Izin `grosir.kelola` dijaga rute; batas outlet pelaku tetap berlaku.
 */
final class KunjunganSalesKontroler extends DasarGrosirKontroler
{
    public function Daftar(Request $permintaan, DaftarKunjunganSales $daftar): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarKunjunganSales::KOLOM_URUT, DaftarKunjunganSales::URUT_BAWAAN, DaftarKunjunganSales::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Grosir/Kunjungan/Daftar', 'Kunjungan', fn (): array => $daftar->Ambil($tabel, $this->IdOutletBoleh()), fn (): array => [
            'OpsiSalesman' => $daftar->OpsiSalesman(),
            'OpsiHasil' => DaftarKunjunganSales::OpsiHasil(),
            'Izin' => $this->AmbilIzinGrosir(),
        ]);
    }

    /** Ekspor CSV sesuai saringan & cari yang aktif di tabel (maks. 5.000 baris terbaru). Koordinat desimal bertitik. */
    public function Ekspor(Request $permintaan, DaftarKunjunganSales $daftar): StreamedResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarKunjunganSales::KOLOM_URUT, DaftarKunjunganSales::URUT_BAWAAN, DaftarKunjunganSales::KOLOM_SARING);

        return PenulisCsvLaporan::Alirkan(
            'kunjungan-salesman',
            ['Tanggal', 'Masuk (UTC)', 'Keluar (UTC)', 'Durasi (menit)', 'Salesman', 'Pelanggan', 'Hasil', 'Pesanan', 'Latitude', 'Longitude', 'Akurasi (m)', 'Catatan'],
            array_map(fn (array $k): array => [
                (string) $k['Tanggal'],
                (string) $k['MasukPada'],
                is_string($k['KeluarPada']) ? $k['KeluarPada'] : '',
                is_int($k['DurasiMenit']) ? $k['DurasiMenit'] : '',
                (string) $k['NamaSalesman'],
                (string) $k['NamaPelanggan'],
                (string) $k['LabelHasil'],
                is_string($k['NomorPesananGrosir']) ? $k['NomorPesananGrosir'] : '',
                is_string($k['Latitude']) ? $k['Latitude'] : '',
                is_string($k['Longitude']) ? $k['Longitude'] : '',
                is_int($k['AkurasiMeter']) ? $k['AkurasiMeter'] : '',
                is_string($k['Catatan']) ? $k['Catatan'] : '',
            ], $daftar->AmbilSemua($tabel, $this->IdOutletBoleh())),
        );
    }
}
