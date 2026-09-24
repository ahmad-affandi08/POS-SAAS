<?php

declare(strict_types=1);

namespace App\Domain\Persediaan\Impor\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Katalog\Impor\Layanan\PembacaBerkasTabel;
use App\Domain\Katalog\Impor\Layanan\PenyimpanBerkasImpor;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Domain\Persediaan\Enum\StatusImporStokAwal;
use App\Domain\Persediaan\Impor\Layanan\PemangkasImporStokAwalLama;
use App\Domain\Persediaan\Impor\Layanan\PemetaKolomImporStokAwal;
use App\Domain\Persediaan\Model\ImporStokAwal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * F-05a impor stok awal langkah 1 (DesainF05a C.7, pola BR-03.6): unggah berkas Excel/CSV + lokasi stok bawaan
 * opsional. Memakai ulang penjaga berkas F-03 (`PembacaBerkasTabel`: jenis dari isi, bom ZIP, CSV dinormalisasi ke
 * UTF-8; `PenyimpanBerkasImpor`: disk privat `impor/{IdTenant}/{ulid}.{ext}`).
 * - Ukuran ≤ `persediaan.Impor.UkuranMaksimalKb` (`BerkasTerlaluBesar`); baris data ≤ `persediaan.Impor.MaksimalBaris`
 *   (`BarisTerlaluBanyak`).
 * - **Idempoten per `HashBerkas`**: berkas yang sama persis selama impor sebelumnya masih aktif (belum Selesai/Gagal/
 *   Dibatalkan) mengembalikan impor itu, tanpa berkas & baris baru.
 * - Impor lama tenant ini (lewat masa simpan) dipangkas lebih dulu. Audit `stok-awal.impor.unggah`.
 * Akses lokasi stok bawaan (outlet pelaku) diperiksa kontroler; di sini lokasi harus ada dan aktif.
 */
final class UnggahImporStokAwal
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PembacaBerkasTabel $pembaca,
        private readonly PenyimpanBerkasImpor $penyimpan,
        private readonly PemangkasImporStokAwalLama $pemangkas,
        private readonly PemetaKolomImporStokAwal $pemeta,
        private readonly InfoGudang $infoGudang,
        private readonly PencatatAudit $audit,
    ) {}

    public function Jalankan(UploadedFile $berkas, ?int $idGudangBawaan, int $idPengguna): ImporStokAwal
    {
        $idTenant = $this->konteks->Wajib();
        $this->pemangkas->Jalankan();

        $maksimalKb = (int) config('persediaan.Impor.UkuranMaksimalKb', 10240);
        $ukuran = (int) $berkas->getSize();

        if ($ukuran > $maksimalKb * 1024) {
            throw new PelanggaranAturanBisnis('BerkasTerlaluBesar', 'Ukuran berkas melebihi '.intdiv($maksimalKb, 1024).' MB. Bagi berkas menjadi beberapa bagian.', 'Berkas');
        }

        if ($idGudangBawaan !== null) {
            $gudang = $this->infoGudang->AmbilBanyak([$idGudangBawaan])[$idGudangBawaan] ?? null;

            if ($gudang === null || ! $gudang->aktif) {
                throw new PelanggaranAturanBisnis('GudangDiarsipkan', 'Lokasi stok bawaan sudah diarsipkan. Pilih lokasi stok lain.', 'UuidGudangBawaan');
            }
        }

        $pathAsli = (string) $berkas->getRealPath();
        $namaBerkas = self::BersihkanNama($berkas->getClientOriginalName());
        $format = $this->pembaca->TentukanFormat($pathAsli, $namaBerkas);
        $isi = (string) file_get_contents($pathAsli);
        $hash = hash('sha256', $isi);

        $ada = $this->CariImporAktif($hash);

        if ($ada !== null) {
            return $ada;
        }

        $pemisah = null;

        if ($format === PembacaBerkasTabel::FORMAT_CSV) {
            $isi = $this->pembaca->NormalisasiCsv($isi);
            $pemisah = $this->pembaca->DeteksiPemisah($isi);
        }

        $path = $this->penyimpan->Simpan($idTenant, $isi, $format);
        unset($isi);

        try {
            $kepala = $this->penyimpan->DenganPathLokal($path, fn (string $lokal): array => $this->pembaca->BacaKepala(
                $lokal,
                $format,
                $pemisah,
                null,
                null,
                (int) config('persediaan.Impor.MaksimalBaris', 20000),
            ));

            $hasil = DB::transaction(function () use ($idPengguna, $idGudangBawaan, $namaBerkas, $path, $hash, $ukuran, $format, $kepala, $pemisah): ImporStokAwal {
                // Kunci baris impor ber-hash sama (indeks IdTenant+HashBerkas): unggahan ganda serentak berurutan.
                $ada = ImporStokAwal::query()->where('HashBerkas', $hash)->orderByDesc('Id')->lockForUpdate()->get()
                    ->first(fn (ImporStokAwal $i): bool => $i->Status->CekAktif());

                if ($ada !== null) {
                    return $ada;
                }

                $impor = ImporStokAwal::query()->create([
                    'IdPengguna' => $idPengguna,
                    'IdGudangBawaan' => $idGudangBawaan,
                    'NamaBerkas' => $namaBerkas,
                    'PathBerkas' => $path,
                    'HashBerkas' => $hash,
                    'UkuranBerkas' => $ukuran,
                    'Format' => $format,
                    'Status' => StatusImporStokAwal::MenungguPemetaan,
                    'KolomSumber' => $kepala['KolomSumber'],
                    'Pemetaan' => $this->pemeta->Petakan($kepala['KolomSumber']),
                    'Opsi' => [
                        'Pembaca' => ['BarisJudul' => $kepala['BarisJudul'], 'PemisahCsv' => $pemisah, 'Lembar' => null],
                        'Peringatan' => [],
                    ],
                    'JumlahBaris' => $kepala['JumlahBaris'],
                ]);

                $this->audit->Catat('stok-awal.impor.unggah', $impor, null, [
                    'NamaBerkas' => $namaBerkas,
                    'Format' => $format,
                    'UkuranBerkas' => $ukuran,
                    'JumlahBaris' => $kepala['JumlahBaris'],
                    'HashBerkas' => $hash,
                    'IdGudangBawaan' => $idGudangBawaan,
                ]);

                return $impor;
            });
        } catch (Throwable $galat) {
            $this->penyimpan->Hapus($path);

            throw $galat;
        }

        if ($hasil->PathBerkas !== $path) {
            // Unggahan serentak berkas yang sama kalah: impor aktif yang ada dipakai, berkas salinan dibuang.
            $this->penyimpan->Hapus($path);
        }

        return $hasil;
    }

    private function CariImporAktif(string $hash): ?ImporStokAwal
    {
        return ImporStokAwal::query()->where('HashBerkas', $hash)->orderByDesc('Id')->get()
            ->first(fn (ImporStokAwal $i): bool => $i->Status->CekAktif());
    }

    private static function BersihkanNama(string $nama): string
    {
        $nama = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', basename($nama)) ?? '';

        return mb_substr(trim($nama) === '' ? 'berkas' : trim($nama), 0, 255);
    }
}
