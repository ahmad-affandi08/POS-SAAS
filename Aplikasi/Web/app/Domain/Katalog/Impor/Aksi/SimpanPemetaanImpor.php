<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Impor\Data\DataOpsiImpor;
use App\Domain\Katalog\Impor\Enum\BidangImpor;
use App\Domain\Katalog\Impor\Enum\StatusImporProduk;
use App\Domain\Katalog\Impor\Layanan\PengirimTugasImpor;
use App\Domain\Katalog\Impor\Model\ImporProduk;
use App\Domain\Katalog\Impor\Model\ImporProdukBaris;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-03 impor langkah 2 (BR-03.6): simpan pemetaan kolom & opsi, lalu periksa data.
 * - `Nama` wajib dipetakan (`KolomWajibBelumDipetakan`); satu kolom hanya untuk satu bidang; indeks harus kolom
 *   berkas. Jenis bawaan tidak boleh induk varian.
 * - Tanpa izin `produk.harga.ubah`, kolom harga yang dipetakan **diabaikan** dan dicatat sebagai peringatan pratinjau.
 *   Kolom Harga Modal/Stok juga diabaikan dengan peringatan (diisi di Stok awal, F-05a).
 * - Dari Pratinjau (ubah pemetaan): hasil validasi lama dihapus. Status → Memvalidasi, lalu tugas validasi dijalankan
 *   langsung (≤ `katalog.Impor.BatasBarisSinkron` baris) atau lewat antrean. Audit `produk.impor.pemetaan`.
 */
final class SimpanPemetaanImpor
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $pemetaan  BidangImpor → indeks kolom|null
     */
    public function Jalankan(ImporProduk $impor, array $pemetaan, DataOpsiImpor $opsi, bool $bolehUbahHarga): ImporProduk
    {
        $impor = DB::transaction(function () use ($impor, $pemetaan, $opsi, $bolehUbahHarga): ImporProduk {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $impor = ImporProduk::query()->whereKey($impor->Id)->lockForUpdate()->firstOrFail();

            if ($impor->Status !== StatusImporProduk::MenungguPemetaan && $impor->Status !== StatusImporProduk::Pratinjau) {
                throw new PelanggaranAturanBisnis('StatusImporTidakValid', "Pemetaan tidak bisa diubah saat impor berstatus {$impor->Status->AmbilLabel()}.", 'Pemetaan');
            }

            if ($opsi->jenisBawaan === JenisProduk::IndukVarian) {
                throw new PelanggaranAturanBisnis('JenisTidakMendukung', 'Jenis bawaan tidak boleh induk varian.', 'Opsi.JenisBawaan');
            }

            [$bersih, $peringatan] = $this->BersihkanPemetaan($impor, $pemetaan, $bolehUbahHarga);

            if ($impor->Status === StatusImporProduk::Pratinjau) {
                $impor->UbahStatus(StatusImporProduk::MenungguPemetaan);
                ImporProdukBaris::query()->where('IdImporProduk', $impor->Id)->delete();
            }

            $lama = ['Pemetaan' => $impor->Pemetaan, 'Opsi' => $impor->Opsi];
            $impor->fill([
                'Pemetaan' => $bersih,
                'Opsi' => $opsi->KeArray() + ['Pembaca' => ($impor->Opsi ?? [])['Pembaca'] ?? [], 'Peringatan' => $peringatan],
                'JumlahValid' => 0,
                'JumlahGalat' => 0,
                'JumlahDilewati' => 0,
                'PesanGalat' => null,
                'DivalidasiPada' => null,
            ]);
            $impor->UbahStatus(StatusImporProduk::Memvalidasi);
            $impor->save();
            $this->audit->Catat('produk.impor.pemetaan', $impor, $lama, ['Pemetaan' => $bersih, 'Opsi' => $opsi->KeArray(), 'Peringatan' => $peringatan]);

            return $impor;
        });

        PengirimTugasImpor::KirimValidasi($impor);

        return $impor->refresh();
    }

    /**
     * @param  array<string, mixed>  $pemetaan
     * @return array{0: array<string, int|null>, 1: list<string>}
     */
    private function BersihkanPemetaan(ImporProduk $impor, array $pemetaan, bool $bolehUbahHarga): array
    {
        $indeksSah = array_column($impor->KolomSumber ?? [], 'Indeks');
        $bersih = [];
        $dipakai = [];
        $hargaDiabaikan = [];
        $diabaikan = [];

        foreach (BidangImpor::cases() as $bidang) {
            $nilai = $pemetaan[$bidang->value] ?? null;

            if ($nilai === null || $nilai === '') {
                $bersih[$bidang->value] = null;

                continue;
            }

            if (! is_numeric($nilai) || ! in_array((int) $nilai, $indeksSah, true)) {
                throw new PelanggaranAturanBisnis('KolomTidakDikenal', 'Kolom yang dipilih tidak ada di berkas.', "Pemetaan.{$bidang->value}");
            }

            $indeks = (int) $nilai;

            if (isset($dipakai[$indeks])) {
                throw new PelanggaranAturanBisnis('KolomGanda', "Kolom ini sudah dipakai untuk {$dipakai[$indeks]}.", "Pemetaan.{$bidang->value}");
            }

            $dipakai[$indeks] = $bidang->AmbilJudul();

            if ($bidang->CekHarga() && ! $bolehUbahHarga) {
                $hargaDiabaikan[] = $bidang->AmbilJudul();
                $bersih[$bidang->value] = null;

                continue;
            }

            if ($bidang->CekDiabaikan()) {
                $diabaikan[] = $bidang->AmbilJudul();
            }

            $bersih[$bidang->value] = $indeks;
        }

        if (($bersih[BidangImpor::Nama->value] ?? null) === null) {
            throw new PelanggaranAturanBisnis('KolomWajibBelumDipetakan', 'Nama Produk wajib dipetakan ke satu kolom.', 'Pemetaan.Nama');
        }

        $peringatan = [];

        if ($hargaDiabaikan !== []) {
            $peringatan[] = 'Kolom harga diabaikan karena Anda tidak punya izin produk.harga.ubah: '.implode(', ', $hargaDiabaikan).'. Produk baru dibuat tanpa harga.';
        }

        if ($diabaikan !== []) {
            $peringatan[] = 'Diabaikan: HPP & stok awal diisi di menu Stok awal ('.implode(', ', $diabaikan).').';
        }

        return [$bersih, $peringatan];
    }
}
