<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Impor\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Impor\Data\DataOpsiImpor;
use App\Domain\Katalog\Impor\Enum\StatusImporProduk;
use App\Domain\Katalog\Impor\Layanan\PemangkasImporLama;
use App\Domain\Katalog\Impor\Layanan\PembacaBerkasTabel;
use App\Domain\Katalog\Impor\Layanan\PembacaPresetImpor;
use App\Domain\Katalog\Impor\Layanan\PemetaKolomOtomatis;
use App\Domain\Katalog\Impor\Layanan\PenyimpanBerkasImpor;
use App\Domain\Katalog\Impor\Model\ImporProduk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * F-03 impor langkah 1 (BR-03.6): unggah berkas Excel/CSV.
 * - Ukuran ≤ `katalog.Impor.UkuranMaksimalKb` (`BerkasTerlaluBesar`); jenis ditentukan dari isi, ekstensi harus
 *   xlsx/csv dan cocok (`BerkasImporTidakValid`); baris data ≤ `katalog.Impor.MaksimalBaris` (`BarisTerlaluBanyak`).
 * - Berkas disimpan privat di `impor/{IdTenant}/{ulid}.{ext}` (CSV sudah dinormalisasi ke UTF-8), hash SHA-256 dari
 *   berkas asli (peringatan "pernah diimpor").
 * - Judul kolom + 3 contoh dibaca sekarang, lalu dipetakan otomatis menurut preset. Status MenungguPemetaan.
 * - Impor lama tenant ini (lewat retensi) dipangkas lebih dulu. Audit `produk.impor.unggah`.
 */
final class UnggahBerkasImpor
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PembacaBerkasTabel $pembaca,
        private readonly PembacaPresetImpor $presetImpor,
        private readonly PemetaKolomOtomatis $pemeta,
        private readonly PenyimpanBerkasImpor $penyimpan,
        private readonly PemangkasImporLama $pemangkas,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(UploadedFile $berkas, string $kodePreset, int $idPengguna): ImporProduk
    {
        $idTenant = $this->konteks->Wajib();
        $preset = $this->presetImpor->Cari($kodePreset) ?? throw new PelanggaranAturanBisnis('BerkasImporTidakValid', 'Format sumber tidak dikenal.', 'Sumber');
        $this->pemangkas->Pangkas();

        $maksimalKb = (int) config('katalog.Impor.UkuranMaksimalKb', 10240);
        $ukuran = (int) $berkas->getSize();

        if ($ukuran > $maksimalKb * 1024) {
            throw new PelanggaranAturanBisnis('BerkasTerlaluBesar', 'Ukuran berkas melebihi '.intdiv($maksimalKb, 1024).' MB. Bagi berkas menjadi beberapa bagian.', 'Berkas');
        }

        $pathAsli = (string) $berkas->getRealPath();
        $namaBerkas = self::BersihkanNama($berkas->getClientOriginalName());
        $format = $this->pembaca->TentukanFormat($pathAsli, $namaBerkas);
        $isi = (string) file_get_contents($pathAsli);
        $hash = hash('sha256', $isi);
        $pemisah = null;

        if ($format === PembacaBerkasTabel::FORMAT_CSV) {
            $isi = $this->pembaca->NormalisasiCsv($isi);
            $pemisah = $preset->pemisahCsv ?? $this->pembaca->DeteksiPemisah($isi);
        }

        $path = $this->penyimpan->Simpan($idTenant, $isi, $format);
        unset($isi);

        try {
            $kepala = $this->penyimpan->DenganPathLokal($path, fn (string $lokal): array => $this->pembaca->BacaKepala(
                $lokal,
                $format,
                $pemisah,
                $preset->lembar,
                $preset->barisJudul,
                (int) config('katalog.Impor.MaksimalBaris', 20000),
            ));

            return DB::transaction(function () use ($idPengguna, $preset, $namaBerkas, $path, $hash, $ukuran, $format, $kepala, $pemisah): ImporProduk {
                $impor = ImporProduk::query()->create([
                    'IdPengguna' => $idPengguna,
                    'Sumber' => $preset->kode,
                    'NamaBerkas' => $namaBerkas,
                    'PathBerkas' => $path,
                    'HashBerkas' => $hash,
                    'UkuranBerkas' => $ukuran,
                    'Format' => $format,
                    'Status' => StatusImporProduk::MenungguPemetaan,
                    'KolomSumber' => $kepala['KolomSumber'],
                    'Pemetaan' => $this->pemeta->Petakan($kepala['KolomSumber'], $preset),
                    'Opsi' => DataOpsiImpor::Bawaan()->KeArray() + [
                        'Pembaca' => ['BarisJudul' => $kepala['BarisJudul'], 'PemisahCsv' => $pemisah, 'Lembar' => $preset->lembar],
                        'Peringatan' => [],
                    ],
                    'JumlahBaris' => $kepala['JumlahBaris'],
                ]);

                $this->audit->Catat('produk.impor.unggah', $impor, null, [
                    'NamaBerkas' => $namaBerkas,
                    'Sumber' => $preset->kode,
                    'Format' => $format,
                    'UkuranBerkas' => $ukuran,
                    'JumlahBaris' => $kepala['JumlahBaris'],
                    'HashBerkas' => $hash,
                ]);

                return $impor;
            });
        } catch (Throwable $galat) {
            $this->penyimpan->Hapus($path);

            throw $galat;
        }
    }

    private static function BersihkanNama(string $nama): string
    {
        $nama = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', basename($nama)) ?? '';

        return mb_substr(trim($nama) === '' ? 'berkas' : trim($nama), 0, 255);
    }
}
