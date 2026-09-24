<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Persediaan\Enum\BidangImporStokAwal;
use App\Domain\Persediaan\Enum\StatusImporStokAwal;
use App\Domain\Persediaan\Impor\Layanan\PengirimTugasImporStokAwal;
use App\Domain\Persediaan\Model\ImporStokAwal;
use App\Domain\Persediaan\Model\ImporStokAwalBaris;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * F-05a impor stok awal langkah 2 (DesainF05a C.7): simpan pemetaan kolom, lokasi stok bawaan, dan tanggal stok
 * awal, lalu periksa data.
 * - Wajib: (SKU atau Barcode atau Nama Produk) + Stok + Harga Modal + (Lokasi Stok atau lokasi bawaan)
 *   (`KolomWajibBelumDipetakan`); satu kolom hanya untuk satu bidang (`KolomGanda`); indeks harus kolom berkas
 *   (`KolomTidakDikenal`). Lokasi bawaan harus aktif (`GudangDiarsipkan`); tanggal tidak boleh melewati hari ini
 *   (`TanggalDiMasaDepan`).
 * - Dari Pratinjau (ubah pemetaan): hasil validasi lama dihapus. Status → Memvalidasi, lalu validasi dijalankan
 *   langsung (≤ `persediaan.Impor.BatasBarisSinkron` baris) atau lewat antrean. Audit `stok-awal.impor.pemetaan`.
 * Akses lokasi bawaan (outlet pelaku) diperiksa kontroler.
 */
final class SimpanPemetaanImporStokAwal
{
    public function __construct(
        private readonly InfoGudang $infoGudang,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  array<string, int|null>  $pemetaan  kunci = BidangImporStokAwal
     */
    public function Jalankan(ImporStokAwal $impor, array $pemetaan, ?int $idGudangBawaan, CarbonImmutable $tanggal): ImporStokAwal
    {
        $idOutletBawaan = null;

        if ($idGudangBawaan !== null) {
            $gudang = $this->infoGudang->AmbilBanyak([$idGudangBawaan])[$idGudangBawaan] ?? null;

            if ($gudang === null || ! $gudang->aktif) {
                throw new PelanggaranAturanBisnis('GudangDiarsipkan', 'Lokasi stok bawaan sudah diarsipkan. Pilih lokasi stok lain.', 'UuidGudangBawaan');
            }

            $idOutletBawaan = $gudang->idOutlet;
        }

        $hariIni = $this->tanggalBisnis->Hitung($idOutletBawaan);

        if ($tanggal->startOfDay()->gt($hariIni)) {
            throw new PelanggaranAturanBisnis('TanggalDiMasaDepan', 'Tanggal stok awal tidak boleh melewati hari ini ('.$hariIni->format('d/m/Y').').', 'Tanggal');
        }

        $impor = DB::transaction(function () use ($impor, $pemetaan, $idGudangBawaan, $tanggal): ImporStokAwal {
            $impor = ImporStokAwal::query()->whereKey($impor->Id)->lockForUpdate()->firstOrFail();

            if ($impor->Status !== StatusImporStokAwal::MenungguPemetaan && $impor->Status !== StatusImporStokAwal::Pratinjau) {
                throw new PelanggaranAturanBisnis('StatusImporTidakValid', "Pemetaan tidak bisa diubah saat impor berstatus {$impor->Status->AmbilLabel()}.", 'Pemetaan');
            }

            $bersih = $this->BersihkanPemetaan($impor, $pemetaan, $idGudangBawaan);

            if ($impor->Status === StatusImporStokAwal::Pratinjau) {
                $impor->UbahStatus(StatusImporStokAwal::MenungguPemetaan);
                ImporStokAwalBaris::query()->where('IdImporStokAwal', $impor->Id)->delete();
            }

            $lama = ['Pemetaan' => $impor->Pemetaan, 'IdGudangBawaan' => $impor->IdGudangBawaan, 'Tanggal' => $impor->Tanggal?->toDateString()];
            $impor->fill([
                'Pemetaan' => $bersih,
                'IdGudangBawaan' => $idGudangBawaan,
                'Tanggal' => $tanggal->toDateString(),
                'Opsi' => ['Pembaca' => ($impor->Opsi ?? [])['Pembaca'] ?? [], 'Peringatan' => []],
                'JumlahValid' => 0,
                'JumlahGalat' => 0,
                'JumlahDokumen' => 0,
                'PesanGalat' => null,
                'DivalidasiPada' => null,
            ]);
            $impor->UbahStatus(StatusImporStokAwal::Memvalidasi);
            $impor->save();
            $this->audit->Catat('stok-awal.impor.pemetaan', $impor, $lama, ['Pemetaan' => $bersih, 'IdGudangBawaan' => $idGudangBawaan, 'Tanggal' => $tanggal->toDateString()]);

            return $impor;
        });

        PengirimTugasImporStokAwal::KirimValidasi($impor);

        return $impor->refresh();
    }

    /**
     * @param  array<string, mixed>  $pemetaan
     * @return array<string, int|null>
     */
    private function BersihkanPemetaan(ImporStokAwal $impor, array $pemetaan, ?int $idGudangBawaan): array
    {
        $indeksSah = array_column($impor->KolomSumber ?? [], 'Indeks');
        $bersih = [];
        $dipakai = [];

        foreach (BidangImporStokAwal::cases() as $bidang) {
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

            $dipakai[$indeks] = $bidang->AmbilLabel();
            $bersih[$bidang->value] = $indeks;
        }

        if ($bersih[BidangImporStokAwal::Sku->value] === null && $bersih[BidangImporStokAwal::Barcode->value] === null && $bersih[BidangImporStokAwal::NamaProduk->value] === null) {
            throw new PelanggaranAturanBisnis('KolomWajibBelumDipetakan', 'Petakan minimal satu kolom pencocok produk: SKU, Barcode, atau Nama Produk.', 'Pemetaan.Sku');
        }

        foreach ([BidangImporStokAwal::Jumlah, BidangImporStokAwal::HargaModal] as $wajib) {
            if ($bersih[$wajib->value] === null) {
                throw new PelanggaranAturanBisnis('KolomWajibBelumDipetakan', "{$wajib->AmbilLabel()} wajib dipetakan ke satu kolom.", "Pemetaan.{$wajib->value}");
            }
        }

        if ($bersih[BidangImporStokAwal::Lokasi->value] === null && $idGudangBawaan === null) {
            throw new PelanggaranAturanBisnis('KolomWajibBelumDipetakan', 'Petakan kolom Lokasi Stok atau pilih lokasi stok bawaan.', 'Pemetaan.Lokasi');
        }

        return $bersih;
    }
}
