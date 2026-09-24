<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Layanan;

use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Data\DataSaringProduk;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Enum\StatusProduk;
use App\Domain\Katalog\Impor\Enum\BidangImpor;
use App\Domain\Katalog\Impor\Kueri\DataEksporProduk;
use App\Domain\Katalog\Impor\Layanan\PenulisTabel;
use App\Domain\Organisasi\Data\DataInfoGudang;
use App\Domain\Persediaan\Enum\BidangImporStokAwal;
use App\Domain\Persediaan\Enum\StatusBarisImporStokAwal;
use App\Domain\Persediaan\Model\ImporStokAwal;
use App\Domain\Persediaan\Model\ImporStokAwalBaris;
use Generator;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Berkas unduhan impor stok awal (DesainF05a C.7) lewat `PenulisTabel` F-03 (streaming, semua sel teks, **anti
 * formula injection** `NetralkanRumus`):
 * - Templat: SKU, Nama Produk, Satuan (informasi), Lokasi Stok, Stok, Harga Modal, Nomor Batch, Kedaluwarsa, Nomor
 *   Seri. `isi=produk` mengisi satu baris per produk berstok aktif (bukan konsinyasi, termasuk anak varian) dari
 *   API ekspor katalog `DataEksporProduk`; lokasi terisi kode lokasi pilihan.
 * - Laporan: kolom asli berkas + "Nomor Baris", "Status Impor", "Galat". `galat` = baris bermasalah saja,
 *   `semua` = seluruh baris. Berkas laporan galat bisa diperbaiki lalu diunggah ulang (judul kolom asli tetap).
 */
final class PenulisBerkasImporStokAwal
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly DataEksporProduk $eksporProduk,
    ) {}

    /**
     * @return list<string>
     */
    public static function AmbilJudulTemplat(): array
    {
        return [
            BidangImporStokAwal::Sku->AmbilLabel(),
            BidangImporStokAwal::NamaProduk->AmbilLabel(),
            'Satuan',
            BidangImporStokAwal::Lokasi->AmbilLabel(),
            BidangImporStokAwal::Jumlah->AmbilLabel(),
            BidangImporStokAwal::HargaModal->AmbilLabel(),
            BidangImporStokAwal::NomorBatch->AmbilLabel(),
            BidangImporStokAwal::TanggalKedaluwarsa->AmbilLabel(),
            BidangImporStokAwal::NomorSeri->AmbilLabel(),
        ];
    }

    public function AlirkanTemplat(string $format, bool $isiProduk, ?DataInfoGudang $gudang): StreamedResponse
    {
        $idTenant = $this->konteks->Wajib();

        return PenulisTabel::Alirkan($format, 'templat-stok-awal', self::AmbilJudulTemplat(), fn (): Generator => $isiProduk
            ? $this->AmbilBarisProduk($idTenant, $gudang === null ? '' : $gudang->kode)
            : self::Kosong());
    }

    public function AlirkanLaporan(ImporStokAwal $impor, string $jenis, string $format): StreamedResponse
    {
        $judulAsli = array_values(array_column($impor->KolomSumber ?? [], 'Judul'));
        $hanyaGalat = $jenis !== 'semua';
        $idTenant = $impor->IdTenant;
        $idImpor = $impor->Id;
        $nama = 'laporan-impor-stok-awal-'.($hanyaGalat ? 'galat-' : '').($impor->DibuatPada?->format('Ymd-His') ?? $impor->Uuid);

        return PenulisTabel::Alirkan($format, $nama, [...$judulAsli, 'Nomor Baris', 'Status Impor', 'Galat'], fn (): Generator => $this->AmbilBarisLaporan($idTenant, $idImpor, $judulAsli, $hanyaGalat));
    }

    /**
     * @return Generator<list<string>>
     */
    private function AmbilBarisProduk(int $idTenant, string $kodeLokasi): Generator
    {
        // Respons dialirkan setelah kontroler selesai: pastikan scope tenant tetap tenant ini.
        $this->konteks->Atur($idTenant);
        $judul = DataEksporProduk::AmbilJudul();
        $indeks = array_flip($judul);
        $ambil = fn (array $baris, BidangImpor $bidang): string => (string) ($baris[$indeks[$bidang->AmbilJudul()] ?? -1] ?? '');
        $jenisBerstok = [];

        foreach (JenisProduk::cases() as $jenis) {
            if ($jenis->CekPunyaStok() && $jenis !== JenisProduk::Konsinyasi) {
                $jenisBerstok[$jenis->AmbilLabel()] = true;
            }
        }

        foreach ($this->eksporProduk->AmbilBaris(new DataSaringProduk(status: StatusProduk::Aktif)) as $baris) {
            if (! isset($jenisBerstok[$ambil($baris, BidangImpor::Jenis)])) {
                continue;
            }

            yield [
                $ambil($baris, BidangImpor::Sku),
                $ambil($baris, BidangImpor::Nama),
                $ambil($baris, BidangImpor::Satuan),
                $kodeLokasi,
                '',
                '',
                '',
                '',
                '',
            ];
        }
    }

    /**
     * @param  list<string>  $judulAsli
     * @return Generator<list<string>>
     */
    private function AmbilBarisLaporan(int $idTenant, int $idImpor, array $judulAsli, bool $hanyaGalat): Generator
    {
        $this->konteks->Atur($idTenant);

        $kueri = ImporStokAwalBaris::query()->where('IdImporStokAwal', $idImpor)
            ->when($hanyaGalat, fn ($k) => $k->where('Status', StatusBarisImporStokAwal::Galat->value));

        foreach ($kueri->lazyById(500, 'Id') as $baris) {
            /** @var ImporStokAwalBaris $baris */
            $galat = array_map(fn (array $g): string => "{$g['Bidang']}: {$g['Pesan']}", $baris->Galat ?? []);

            yield [
                ...array_map(fn (string $judul): string => (string) ($baris->DataAsli[$judul] ?? ''), $judulAsli),
                (string) $baris->NomorBaris,
                $baris->Status->AmbilLabel(),
                implode('; ', $galat),
            ];
        }
    }

    /**
     * @return Generator<list<string>>
     */
    private static function Kosong(): Generator
    {
        yield from [];
    }
}
