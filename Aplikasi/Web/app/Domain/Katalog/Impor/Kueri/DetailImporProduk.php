<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Kueri;

use App\Domain\Katalog\Impor\Data\DataOpsiImpor;
use App\Domain\Katalog\Impor\Enum\AksiBarisImpor;
use App\Domain\Katalog\Impor\Enum\BidangImpor;
use App\Domain\Katalog\Impor\Enum\StatusBarisImpor;
use App\Domain\Katalog\Impor\Enum\StatusImporProduk;
use App\Domain\Katalog\Impor\Layanan\PemeriksaBatasSkuImpor;
use App\Domain\Katalog\Impor\Model\ImporProduk;
use App\Domain\Katalog\Impor\Model\ImporProdukBaris;
use App\Domain\Pajak\Kueri\DaftarKelompokPajak;

/**
 * Props halaman detail impor (F-03 E.10 `PropsDetailImpor.Pemetaan` & `.Pratinjau`) dan status JSON polling (D.2).
 */
final class DetailImporProduk
{
    public const MAKSIMAL_BARIS_GALAT = 100;

    public function __construct(
        private readonly DaftarKelompokPajak $kelompokPajak,
        private readonly PemeriksaBatasSkuImpor $batasSku,
    ) {}

    /**
     * Null bila pemetaan tidak bisa diubah lagi (hanya MenungguPemetaan & Pratinjau).
     *
     * @return array<string, mixed>|null
     */
    public function AmbilPemetaan(ImporProduk $impor): ?array
    {
        if (! in_array($impor->Status, [StatusImporProduk::MenungguPemetaan, StatusImporProduk::Pratinjau], true)) {
            return null;
        }

        $opsi = DataOpsiImpor::DariArray($impor->Opsi ?? []);
        $uuidPajak = array_column($this->kelompokPajak->AmbilOpsi(), 'Uuid', 'Id');
        $pemetaan = [];

        foreach (BidangImpor::cases() as $bidang) {
            $nilai = ($impor->Pemetaan ?? [])[$bidang->value] ?? null;
            $pemetaan[$bidang->value] = is_int($nilai) ? $nilai : null;
        }

        return [
            'KolomSumber' => array_values($impor->KolomSumber ?? []),
            'Bidang' => array_map(fn (BidangImpor $bidang): array => [
                'Kunci' => $bidang->value,
                'Label' => $bidang->AmbilJudul(),
                'Wajib' => $bidang->CekWajib(),
                'Harga' => $bidang->CekHarga(),
                'Keterangan' => $bidang->AmbilKeterangan(),
            ], BidangImpor::cases()),
            'Pemetaan' => $pemetaan,
            'Opsi' => [
                'Mode' => $opsi->mode->value,
                'UuidKelompokPajakBawaan' => $opsi->idKelompokPajakBawaan === null ? null : ($uuidPajak[$opsi->idKelompokPajakBawaan] ?? null),
                'JenisBawaan' => $opsi->jenisBawaan->value,
                'BuatKategoriBaru' => $opsi->buatKategoriBaru,
                'BuatSatuanBaru' => $opsi->buatSatuanBaru,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function AmbilPratinjau(ImporProduk $impor): ?array
    {
        if ($impor->Status !== StatusImporProduk::Pratinjau) {
            return null;
        }

        $aksi = ImporProdukBaris::query()->where('IdImporProduk', $impor->Id)
            ->whereIn('Status', [StatusBarisImpor::Valid->value, StatusBarisImpor::Dilewati->value])
            ->selectRaw('Aksi, COUNT(*) AS Jumlah')->groupBy('Aksi')->pluck('Jumlah', 'Aksi')->all();
        $galat = ImporProdukBaris::query()->where('IdImporProduk', $impor->Id)->where('Status', StatusBarisImpor::Galat->value)
            ->orderBy('NomorBaris')->limit(self::MAKSIMAL_BARIS_GALAT)->get(['NomorBaris', 'Galat', 'Data', 'DataAsli']);

        return [
            'BarisGalat' => array_values($galat->map(fn (ImporProdukBaris $baris): array => [
                'NomorBaris' => $baris->NomorBaris,
                'Galat' => array_values($baris->Galat ?? []),
                'Data' => ['Nama' => (string) ($baris->Data['Nama'] ?? '')] + array_map('strval', $baris->DataAsli),
            ])->all()),
            'RingkasanAksi' => [
                'Buat' => (int) ($aksi[AksiBarisImpor::Buat->value] ?? 0),
                'Perbarui' => (int) ($aksi[AksiBarisImpor::Perbarui->value] ?? 0),
                'Lewati' => (int) ($aksi[AksiBarisImpor::Lewati->value] ?? 0),
            ],
            'Peringatan' => $impor->AmbilPeringatan(),
            'DiblokirBatasSku' => $this->batasSku->Periksa($impor),
        ];
    }

    /**
     * Status ringkas untuk polling tiap 3 detik (tipe FE `StatusImpor`).
     *
     * @return array{Status: string, LabelStatus: string, Progres: int, JumlahDiterapkan: int, JumlahGagal: int, PesanGalat: string|null}
     */
    public function AmbilStatus(ImporProduk $impor): array
    {
        return [
            'Status' => $impor->Status->value,
            'LabelStatus' => $impor->Status->AmbilLabel(),
            'Progres' => DaftarImporProduk::HitungProgres($impor),
            'JumlahDiterapkan' => $impor->JumlahDiterapkan,
            'JumlahGagal' => $impor->JumlahGagal,
            'PesanGalat' => $impor->PesanGalat,
        ];
    }
}
