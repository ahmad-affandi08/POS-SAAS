<?php

declare(strict_types=1);

namespace App\Domain\Bengkel\Layanan;

use App\Domain\Bengkel\Enum\JenisBarisPerintahKerja;
use App\Domain\Bengkel\Enum\StatusPerintahKerja;
use App\Domain\Bengkel\Model\PerintahKerja;
use App\Domain\Bengkel\Model\PerintahKerjaDetail;
use App\Domain\Bersama\Dokumen\Layanan\PencatatRiwayatStatus;
use App\Domain\Karyawan\Kueri\JadwalStafReservasi;
use App\Domain\Katalog\Kueri\InfoProdukStok;

/**
 * Layanan publik domain Bengkel untuk domain Penjualan (§9.10), dijalankan di transaksi DB penjualan itu sendiri:
 *
 * - **Tagih**: penjualan kasir yang membawa `UuidPerintahKerja` menautkan perintah kerja yang sudah disetujui dan
 *   belum ditagih (Disetujui/Dikerjakan/Qc/Selesai) lalu menandainya Ditagih. Stok sparepart & jurnal bergerak lewat
 *   penjualan itu (alur F-07 biasa), bukan di sini. Perintah kerja tidak dikenal, di outlet lain, belum disetujui, atau
 *   sudah ditagih penjualan lain = tidak ditautkan dan dilaporkan sebagai masalah: penjualan tetap diterima (aplikasi
 *   kasir offline-first tidak boleh gagal karena dokumen pendamping) dan ditandai untuk ditinjau.
 * - Mekanik baris jasa yang disetujui diberikan sebagai **antrean staf per produk** supaya baris penjualan tanpa staf
 *   mendapat mekaniknya (komisi F-18 tetap lewat `PencatatKomisiPenjualan`, tidak ada jalur komisi kedua).
 * - **Batalkan**: void penjualan itu mengembalikan perintah kerja ke Selesai dan melepas tautannya, sehingga bisa
 *   ditagih ulang.
 */
final class PenagihPerintahKerjaPenjualan
{
    public function __construct(
        private readonly PencatatRiwayatStatus $riwayat,
        private readonly JadwalStafReservasi $staf,
        private readonly InfoProdukStok $produk,
    ) {}

    /**
     * @param  list<string>  $uuidProdukPenjualan  produk di baris penjualan (untuk memeriksa baris yang tertinggal)
     * @return array{0: list<string>, 1: array<string, list<list<string>>>} masalah untuk tinjauan & antrean Uuid mekanik per Uuid produk
     */
    public function Tagih(string $uuidPerintahKerja, int $idPenjualan, int $idOutlet, ?int $idPengguna, array $uuidProdukPenjualan): array
    {
        $pk = PerintahKerja::query()->where('Uuid', $uuidPerintahKerja)->lockForUpdate()->first();

        if ($pk === null || $pk->IdOutlet !== $idOutlet) {
            return [['perintah kerja tidak ditemukan di outlet ini'], []];
        }

        if ($pk->IdPenjualan !== null && $pk->IdPenjualan !== $idPenjualan) {
            return [["perintah kerja {$pk->Nomor} sudah ditagih lewat penjualan lain"], []];
        }

        if ($pk->IdPenjualan === null && ! $pk->Status->CekSiapTagih()) {
            return [["perintah kerja {$pk->Nomor} berstatus {$pk->Status->AmbilLabel()}, belum bisa ditagih"], []];
        }

        if ($pk->IdPenjualan === null) {
            $dari = $pk->Status;
            $pk->UbahStatus(StatusPerintahKerja::Ditagih);
            $pk->IdPenjualan = $idPenjualan;
            $pk->DitagihPada = now();
            $pk->save();
            $this->riwayat->Catat(PerintahKerja::JENIS_DOKUMEN, $pk->Id, $dari->value, StatusPerintahKerja::Ditagih->value, $idPengguna, 'Ditagih di kasir');
        }

        $disetujui = PerintahKerjaDetail::query()->where('IdPerintahKerja', $pk->Id)->where('Disetujui', true)->orderBy('Urutan')->get();
        $produk = $this->AmbilUuidProduk(array_values($disetujui->pluck('IdProduk')->all()));
        $mekanik = $this->staf->AmbilRingkas(array_values(array_filter($disetujui->pluck('IdKaryawan')->all(), 'is_int')));
        $antrean = [];
        $masalah = [];

        foreach ($disetujui as $d) {
            $uuidProduk = $produk[$d->IdProduk] ?? null;

            if ($uuidProduk === null) {
                continue;
            }

            if (! in_array($uuidProduk, $uuidProdukPenjualan, true)) {
                $masalah[] = "{$d->NamaProduk} dari {$pk->Nomor} tidak ikut ditagih";
            }

            if ($d->Jenis === JenisBarisPerintahKerja::Jasa && $d->IdKaryawan !== null && isset($mekanik[$d->IdKaryawan])) {
                $antrean[$uuidProduk][] = [$mekanik[$d->IdKaryawan]['Uuid']];
            }
        }

        return [$masalah, $antrean];
    }

    /** Void penjualan: perintah kerja yang ditagihnya kembali Selesai dan bisa ditagih ulang. */
    public function Batalkan(int $idPenjualan, ?int $idPengguna): void
    {
        $pk = PerintahKerja::query()->where('IdPenjualan', $idPenjualan)->lockForUpdate()->first();

        if ($pk === null || $pk->Status !== StatusPerintahKerja::Ditagih) {
            return;
        }

        $pk->UbahStatus(StatusPerintahKerja::Selesai);
        $pk->IdPenjualan = null;
        $pk->DitagihPada = null;
        $pk->save();
        $this->riwayat->Catat(PerintahKerja::JENIS_DOKUMEN, $pk->Id, StatusPerintahKerja::Ditagih->value, StatusPerintahKerja::Selesai->value, $idPengguna, 'Penjualan di-void');
    }

    /**
     * @param  list<mixed>  $idProduk
     * @return array<int, string>
     */
    private function AmbilUuidProduk(array $idProduk): array
    {
        return array_map(fn ($p): string => $p->uuid, $this->produk->AmbilBanyak(array_values(array_filter($idProduk, 'is_int')), true));
    }
}
