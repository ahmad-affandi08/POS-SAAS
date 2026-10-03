<?php

declare(strict_types=1);

namespace App\Domain\Laporan\Aksi;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Web\AlamatDomain;
use App\Domain\Laporan\Kueri\InsightMingguan;
use App\Domain\Laporan\Model\LanggananInsightMingguan;
use App\Domain\Laporan\Surel\InsightMingguan as SurelInsightMingguan;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\KontakAnggotaTenant;
use App\Domain\Organisasi\Kueri\KonteksTindakanPengguna;
use App\Domain\Tenant\Kueri\ProfilTenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * X6 insight mingguan (v3.79): tiap Senin pagi kirim ringkasan minggu lalu ke anggota tenant aktif yang berlangganan
 * dan boleh melihat laporan penjualan (Owner bawaan berlangganan). Angka mengikuti outlet akses penerima. Tanpa
 * penjualan di dua minggu terakhir = tidak dikirim. Paling banyak sekali per minggu per penerima (`TerakhirDikirim` =
 * Senin minggu laporan). Dipanggil dalam konteks tenant.
 */
final class KirimInsightMingguan
{
    public function __construct(
        private readonly KontakAnggotaTenant $kontak,
        private readonly KonteksTindakanPengguna $konteks,
        private readonly InsightMingguan $insight,
        private readonly ProfilTenant $profil,
    ) {}

    /** @return int jumlah email terkirim */
    public function Jalankan(int $idTenant, CarbonImmutable $hariIni): int
    {
        $langganan = LanggananInsightMingguan::query()->get()->keyBy('IdPengguna');
        $namaUsaha = $this->profil->Ambil($idTenant)['Nama'];
        $senin = $hariIni->startOfWeek(CarbonImmutable::MONDAY)->subWeek();
        $terkirim = 0;
        $simpanan = [];

        foreach ($this->kontak->Ambil($idTenant) as $anggota) {
            $baris = $langganan->get($anggota['Id']);

            if (! ($baris->Aktif ?? $anggota['Pemilik']) || $baris?->TerakhirDikirim?->toDateString() === $senin->toDateString()) {
                continue;
            }

            $konteks = $this->konteks->Buat($idTenant, $anggota['Id'], $hariIni);

            if (! $konteks->CekIzin(IzinTenant::LaporanPenjualanLihat->value)) {
                continue;
            }

            $kunci = $konteks->idOutletBoleh === null ? 'semua' : implode(',', $konteks->idOutletBoleh);
            $isi = $simpanan[$kunci] ??= $this->insight->Susun($konteks->idOutletBoleh, $hariIni);

            if ($isi === null) {
                continue;
            }

            // Saran restock = data stok; halaman sumbernya (`laporan/stok?tab=restock`) mensyaratkan `persediaan.lihat`.
            if (! $konteks->CekIzin(IzinTenant::PersediaanLihat->value)) {
                $isi['Restock'] = [];
            }

            try {
                Mail::to($anggota['Email'])->send(new SurelInsightMingguan(
                    $anggota['Nama'],
                    $namaUsaha,
                    $senin->translatedFormat('j M').' – '.$senin->addDays(6)->translatedFormat('j M Y'),
                    self::FormatTampil($isi),
                    AlamatDomain::BuatUrlAbsolutTenant("/kelola/laporan/penjualan?dari={$isi['Dari']}&sampai={$isi['Sampai']}"),
                    AlamatDomain::BuatUrlAbsolutTenant('/kelola/laporan/stok?tab=restock'),
                ));
            } catch (Throwable $galat) {
                // Transport email gagal: dicoba lagi pada putaran berikutnya (belum ditandai terkirim).
                report($galat);

                continue;
            }

            $baris ??= new LanggananInsightMingguan(['IdPengguna' => $anggota['Id'], 'Aktif' => true]);
            $baris->setAttribute('TerakhirDikirim', $senin->toDateString());
            $baris->save();
            $terkirim++;
        }

        return $terkirim;
    }

    /**
     * Angka siap tampil di email (rupiah, persen berkoma, tanggal Indonesia).
     *
     * @param  array<string, mixed>  $isi
     * @return array<string, mixed>
     */
    public static function FormatTampil(array $isi): array
    {
        $rupiah = fn (mixed $nilai): string => Uang::Dari(is_string($nilai) ? $nilai : '0')->FormatRupiah();
        $persen = is_string($isi['PersenPerubahan'] ?? null) ? $isi['PersenPerubahan'] : null;

        return [
            'Bersih' => $rupiah($isi['Bersih']),
            'BersihSebelumnya' => $rupiah($isi['BersihSebelumnya']),
            'Perubahan' => $persen === null ? null : (str_starts_with($persen, '-') ? 'turun ' : 'naik ').str_replace('.', ',', ltrim($persen, '-')).'%',
            'JumlahTransaksi' => $isi['JumlahTransaksi'],
            'JumlahTransaksiSebelumnya' => $isi['JumlahTransaksiSebelumnya'],
            'RataTransaksi' => $rupiah($isi['RataTransaksi']),
            'HariTeramai' => is_array($isi['HariTeramai'] ?? null)
                ? CarbonImmutable::parse($isi['HariTeramai']['Tanggal'])->translatedFormat('l, j F').' ('.$rupiah($isi['HariTeramai']['Bersih']).')'
                : null,
            'Terlaris' => array_map(fn (array $p): array => ['Nama' => $p['NamaProduk'], 'Bersih' => $rupiah($p['Bersih'])], (array) $isi['Terlaris']),
            'Naik' => array_map(fn (array $p): array => ['Nama' => $p['NamaProduk'], 'Selisih' => '+'.$rupiah($p['Selisih'])], (array) $isi['Naik']),
            'Turun' => array_map(fn (array $p): array => ['Nama' => $p['NamaProduk'], 'Selisih' => $rupiah($p['Selisih'])], (array) $isi['Turun']),
            'Restock' => array_map(fn (array $r): array => [
                'Nama' => $r['NamaProduk'],
                'Lokasi' => $r['NamaGudang'],
                'Habis' => $r['HariHabis'] === 0 ? 'sudah habis' : "habis ±{$r['HariHabis']} hari lagi",
                'Saran' => rtrim(rtrim(str_replace('.', ',', $r['SaranBeli']), '0'), ',').' '.$r['SimbolSatuan'],
            ], (array) $isi['Restock']),
            'Lebaran' => is_array($isi['Lebaran'] ?? null)
                ? 'Idul Fitri '.CarbonImmutable::parse($isi['Lebaran']['Tanggal'])->translatedFormat('j F Y').' tinggal '.$isi['Lebaran']['SisaHari'].' hari. Saran restock sudah memperhitungkan penjualan Ramadan tahun lalu.'
                : null,
        ];
    }
}
