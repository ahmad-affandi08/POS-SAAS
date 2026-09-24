<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Persediaan\Enum\BidangImporStokAwal;
use App\Domain\Persediaan\Enum\StatusBarisImporStokAwal;
use App\Domain\Persediaan\Enum\StatusImporStokAwal;
use App\Domain\Persediaan\Impor\Layanan\PemetaKolomImporStokAwal;
use App\Domain\Persediaan\Model\ImporStokAwal;
use App\Domain\Persediaan\Model\ImporStokAwalBaris;
use App\Domain\Persediaan\Model\StokAwal;

/**
 * Detail impor stok awal (tipe FE `PropsDetailImporStokAwal` tanpa `OpsiGudang`, DesainF05a E) dan status JSON
 * polling. Pratinjau merangkum draf yang akan dibuat: per lokasi stok, dipecah per
 * `persediaan.StokAwal.MaksimalBaris` baris (urutan sama dengan `PenerapImporStokAwal`), dengan total nilai
 * Σ Nilai baris (uang, tanpa float).
 */
final class DetailImporStokAwal
{
    public const MAKSIMAL_BARIS_GALAT = 100;

    public function __construct(
        private readonly DaftarImporStokAwal $daftar,
        private readonly InfoGudang $infoGudang,
        private readonly TanggalBisnisOutlet $tanggalBisnis,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function Ambil(ImporStokAwal $impor): array
    {
        return [
            'Impor' => $this->daftar->AmbilRingkasan($impor),
            'Pemetaan' => $this->AmbilPemetaan($impor),
            'Pratinjau' => $this->AmbilPratinjau($impor),
            'Dokumen' => $this->AmbilDokumen($impor),
        ];
    }

    /**
     * Status ringkas untuk polling tiap 3 detik.
     *
     * @return array{Status: string, LabelStatus: string, Progres: int, JumlahDokumen: int, PesanGalat: string|null}
     */
    public function AmbilStatus(ImporStokAwal $impor): array
    {
        return [
            'Status' => $impor->Status->value,
            'LabelStatus' => $impor->Status->AmbilLabel(),
            'Progres' => DaftarImporStokAwal::HitungProgres($impor),
            'JumlahDokumen' => $impor->JumlahDokumen,
            'PesanGalat' => $impor->PesanGalat,
        ];
    }

    /**
     * Null bila pemetaan tidak bisa diubah lagi (hanya MenungguPemetaan & Pratinjau).
     *
     * @return array<string, mixed>|null
     */
    private function AmbilPemetaan(ImporStokAwal $impor): ?array
    {
        if (! in_array($impor->Status, [StatusImporStokAwal::MenungguPemetaan, StatusImporStokAwal::Pratinjau], true)) {
            return null;
        }

        $gudang = $impor->IdGudangBawaan === null ? null : ($this->infoGudang->AmbilBanyak([$impor->IdGudangBawaan])[$impor->IdGudangBawaan] ?? null);
        $pemetaan = [];

        foreach (BidangImporStokAwal::cases() as $bidang) {
            $nilai = ($impor->Pemetaan ?? [])[$bidang->value] ?? null;
            $pemetaan[$bidang->value] = is_int($nilai) ? $nilai : null;
        }

        return [
            'KolomSumber' => array_values($impor->KolomSumber ?? []),
            'Bidang' => array_map(fn (BidangImporStokAwal $bidang): array => [
                'Kunci' => $bidang->value,
                'Label' => $bidang->AmbilLabel(),
                'Wajib' => PemetaKolomImporStokAwal::CekWajib($bidang),
                'Keterangan' => PemetaKolomImporStokAwal::AmbilKeterangan($bidang),
            ], BidangImporStokAwal::cases()),
            'Pemetaan' => $pemetaan,
            'UuidGudangBawaan' => $gudang?->uuid,
            'Tanggal' => $impor->Tanggal?->toDateString() ?? $this->tanggalBisnis->Hitung($gudang?->idOutlet)->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function AmbilPratinjau(ImporStokAwal $impor): ?array
    {
        if ($impor->Status !== StatusImporStokAwal::Pratinjau) {
            return null;
        }

        $galat = ImporStokAwalBaris::query()->where('IdImporStokAwal', $impor->Id)->where('Status', StatusBarisImporStokAwal::Galat->value)
            ->orderBy('NomorBaris')->limit(self::MAKSIMAL_BARIS_GALAT)->get(['NomorBaris', 'Galat', 'Data', 'DataAsli']);

        return [
            'BarisGalat' => array_values($galat->map(fn (ImporStokAwalBaris $baris): array => [
                'NomorBaris' => $baris->NomorBaris,
                'Galat' => array_values($baris->Galat ?? []),
                'Data' => ['Produk' => self::LabelProduk($baris->Data ?? [])] + array_map('strval', $baris->DataAsli),
            ])->all()),
            'RingkasanDokumen' => $this->SusunRingkasanDokumen($impor),
            'Peringatan' => PemetaKolomImporStokAwal::AmbilPeringatan($impor),
        ];
    }

    /**
     * @return list<array{NamaGudang: string, JumlahBaris: int, TotalNilai: string}>
     */
    private function SusunRingkasanDokumen(ImporStokAwal $impor): array
    {
        $maksimal = max(1, (int) config('persediaan.StokAwal.MaksimalBaris', 2000));
        $perGudang = [];

        foreach (ImporStokAwalBaris::query()->where('IdImporStokAwal', $impor->Id)->where('Status', StatusBarisImporStokAwal::Valid->value)
            ->select(['Id', 'NomorBaris', 'Data'])->lazyById(1000, 'Id') as $baris) {
            // Urut Id = urut NomorBaris (baris disisipkan berurutan saat validasi).
            /** @var ImporStokAwalBaris $baris */
            $perGudang[(int) ($baris->Data['IdGudang'] ?? 0)][] = (string) ($baris->Data['Nilai'] ?? '0');
        }

        ksort($perGudang);
        $nama = $this->infoGudang->AmbilBanyak(array_keys($perGudang));
        $hasil = [];

        foreach ($perGudang as $idGudang => $nilai) {
            foreach (array_chunk($nilai, $maksimal) as $potongan) {
                $total = Uang::Nol();

                foreach ($potongan as $satu) {
                    $total = $total->Tambah(Uang::Dari($satu));
                }

                $hasil[] = ['NamaGudang' => $nama[$idGudang]->nama ?? '—', 'JumlahBaris' => count($potongan), 'TotalNilai' => $total->KeString()];
            }
        }

        return $hasil;
    }

    /**
     * @return list<array{Uuid: string, NamaGudang: string, JumlahBaris: int, Status: string}>
     */
    private function AmbilDokumen(ImporStokAwal $impor): array
    {
        $dokumen = StokAwal::query()->where('IdImporStokAwal', $impor->Id)->orderBy('Id')->get(['Id', 'Uuid', 'IdGudang', 'JumlahBaris', 'Status']);
        $nama = $this->infoGudang->AmbilBanyak(array_values(array_unique($dokumen->pluck('IdGudang')->all())));

        return array_values($dokumen->map(fn (StokAwal $s): array => [
            'Uuid' => $s->Uuid,
            'NamaGudang' => $nama[$s->IdGudang]->nama ?? '—',
            'JumlahBaris' => $s->JumlahBaris,
            'Status' => $s->Status->value,
        ])->all());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function LabelProduk(array $data): string
    {
        foreach (['NamaProduk', 'Sku', 'Barcode'] as $kolom) {
            if (is_string($data[$kolom] ?? null) && $data[$kolom] !== '') {
                return $data[$kolom];
            }
        }

        return '—';
    }
}
