<?php

declare(strict_types=1);

namespace App\Domain\Kasir\Kueri;

use App\Domain\Kasir\Model\KategoriKas;
use App\Domain\Organisasi\Kueri\OutletPenjualan;
use App\Domain\Organisasi\Kueri\ProfilPajakOutlet;
use App\Domain\Organisasi\Kueri\StafPerangkat;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Organisasi\Layanan\VerifierPinOffline;
use App\Domain\Organisasi\Model\Perangkat;
use App\Domain\Pajak\Kueri\TarifPajakBerlaku;
use App\Domain\Penjualan\Kueri\DaftarMetodePembayaran;
use App\Domain\Penjualan\Kueri\NomorUrutPenjualanPerangkat;
use App\Domain\Tenant\Kueri\PengaturanKasirTenant;
use stdClass;

/**
 * Isi `GET /api/pos/v1/data-awal` (PRD §16.3). F-06: staf & verifier PIN offline, kategori kas aktif, pengaturan
 * kasir, dan parameter Argon2id. F-07b: batas diskon & pembulatan tunai di `Pengaturan`, identitas `Outlet` &
 * `Perangkat` (nomor BR-07.1, struk), `ProfilPajak` outlet, `TarifPajak` terbit (nasional + kota outlet, belum
 * berakhir, termasuk yang akan berlaku), dan `MetodePembayaran` aktif jenis fase 1. Katalog tetap lewat `/katalog`
 * (F-03); bagian lain (promo, meja) ditambahkan flow masing-masing. PRD v1.46: `Perangkat.NomorUrutPenjualan`
 * = objek `{"YYMMDD": urut terakhir}` penjualan perangkat ini (14 hari terakhir) agar pemasangan ulang aplikasi tidak
 * memakai nomor yang sama (`NomorUrutRetur` sama untuk nomor retur `RJ/...`); `TarifPajak[].Kategori` = kategori jenis pajak (`Ppn`/`Pbjt`/`Lainnya`).
 */
final class DataAwalKasir
{
    public function __construct(
        private readonly StafPerangkat $staf,
        private readonly PengaturanKasirTenant $pengaturan,
        private readonly OutletPenjualan $outlet,
        private readonly ProfilPajakOutlet $profilPajak,
        private readonly TarifPajakBerlaku $tarifPajak,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly DaftarMetodePembayaran $metodePembayaran,
        private readonly NomorUrutPenjualanPerangkat $nomorUrut,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function Ambil(Perangkat $perangkat): array
    {
        $pengaturan = $this->pengaturan->Ambil();
        $outlet = $this->outlet->Ambil($perangkat->IdOutlet, $perangkat->Id);
        $profil = $this->profilPajak->Ambil($perangkat->IdOutlet);
        $hariIni = $this->tanggalBisnis->Hitung($perangkat->IdOutlet);
        $nomorUrut = $this->nomorUrut->Ambil($perangkat->Id, $hariIni);
        $nomorUrutRetur = $this->nomorUrut->AmbilRetur($perangkat->Id, $hariIni);

        return [
            'Pengaturan' => [
                'BatasKasKeluar' => $pengaturan->batasKasKeluar->KeString(),
                'ShiftBersama' => $pengaturan->shiftBersama,
                'BatasDiskonManual' => (string) $pengaturan->batasDiskonManual,
                'BatasDiskonPenyetuju' => (string) $pengaturan->batasDiskonPenyetuju,
                'PembulatanTunai' => $pengaturan->AmbilPembulatanTunaiLarik(),
                // F-11: tutup shift buta & toleransi selisih kas (di atasnya wajib alasan + PIN shift.selisih.setujui).
                'TutupShiftButa' => $pengaturan->tutupShiftButa,
                'ToleransiSelisihKas' => $pengaturan->toleransiSelisihKas->KeString(),
                // F-09: batas hari retur sejak tanggal bisnis penjualan (aplikasi memeriksa sebelum mengirim retur).
                'BatasHariRetur' => $pengaturan->batasHariRetur,
                // F-12: tempo butuh penyetuju bila pelanggan punya piutang lewat jatuh tempo lebih dari N hari (BR-12.1).
                'BatasHariLewatJatuhTempo' => $pengaturan->batasHariLewatJatuhTempo,
            ],
            'Outlet' => $outlet === null ? null : [
                'Uuid' => $outlet->uuidOutlet,
                'Kode' => $outlet->kodeOutlet,
                'Nama' => $outlet->namaOutlet,
                'Alamat' => $outlet->alamat,
                'Telepon' => $outlet->telepon,
                // Tambahan di luar PRD (aditif): perangkat menghitung tanggal bisnis & nomor BR-07.1 offline.
                'ZonaWaktu' => $outlet->zonaWaktu,
                'JamTutupBuku' => $outlet->jamTutupBuku,
            ],
            'Perangkat' => [
                'Uuid' => $perangkat->Uuid,
                'Kode' => $perangkat->Kode,
                // Objek JSON walau kosong (`{}`), bukan larik.
                'NomorUrutPenjualan' => $nomorUrut === [] ? new stdClass : $nomorUrut,
                'NomorUrutRetur' => $nomorUrutRetur === [] ? new stdClass : $nomorUrutRetur,
            ],
            'ProfilPajak' => [
                'Pkp' => $profil->pkp ?? false,
                'PungutPbjt' => $profil->pungutPbjt ?? false,
                'HargaTermasukPajak' => $profil->hargaTermasukPajak ?? false,
                'BiayaLayanan' => ['Aktif' => $profil->biayaLayananAktif ?? false, 'Persen' => $profil->persenBiayaLayanan ?? '0.00'],
            ],
            'TarifPajak' => $this->tarifPajak->DaftarUntukOutlet($outlet?->kodeKota, $hariIni),
            'MetodePembayaran' => $this->metodePembayaran->AmbilUntukPos(),
            'KategoriKas' => array_values(KategoriKas::query()
                ->where('Aktif', true)
                ->orderBy('Jenis')
                ->orderBy('Urutan')
                ->orderBy('Nama')
                ->get()
                ->map(fn (KategoriKas $k): array => ['Uuid' => $k->Uuid, 'Nama' => $k->Nama, 'Jenis' => $k->Jenis->value])
                ->all()),
            'Staf' => $this->staf->Ambil($perangkat),
            'PinOffline' => [
                'Tersedia' => $perangkat->KunciPinOffline !== null,
                'Parameter' => VerifierPinOffline::AmbilParameter(),
                'BatasSalah' => 5,
                'MenitKunci' => 5,
            ],
        ];
    }
}
