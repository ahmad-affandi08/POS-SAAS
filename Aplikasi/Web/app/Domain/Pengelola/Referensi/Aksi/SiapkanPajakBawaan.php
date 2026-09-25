<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Referensi\Aksi;

use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pajak\Enum\CakupanPajak;
use App\Domain\Pajak\Enum\KategoriJenisPajak;
use App\Domain\Pajak\Model\JenisPajak;
use App\Domain\Pajak\Model\TarifPajak;
use RuntimeException;

/**
 * Jenis pajak bawaan (PRD §12.1) dan DRAF tarif awal dari file data `database/Data/TarifPajakAwal.json`. Idempoten.
 *
 * BR-P02.5: angka tarif tidak pernah ditulis di kode. Draf wajib diverifikasi konsultan pajak, diberi tanggal berlaku,
 * lalu diajukan & disetujui four-eyes sebelum terbit (BR-P02.2). Tidak ada tarif yang langsung terbit dari seeder.
 */
final class SiapkanPajakBawaan
{
    public function Jalankan(?string $pathData = null): void
    {
        JenisPajak::query()->firstOrCreate(['Kode' => 'Ppn'], ['Nama' => 'PPN', 'Cakupan' => CakupanPajak::Nasional, 'Kategori' => KategoriJenisPajak::Ppn]);
        JenisPajak::query()->firstOrCreate(
            ['Kode' => 'PbjtMakananMinuman'],
            ['Nama' => 'PBJT makanan & minuman (PB1)', 'Cakupan' => CakupanPajak::Daerah, 'Kategori' => KategoriJenisPajak::Pbjt],
        );

        foreach (self::BacaData($pathData ?? database_path('Data/TarifPajakAwal.json')) as $data) {
            $jenis = JenisPajak::query()->where('Kode', $data['KodeJenisPajak'])->firstOrFail();

            if (TarifPajak::query()->where('IdJenisPajak', $jenis->Id)->exists()) {
                continue;
            }

            TarifPajak::query()->create([
                'IdJenisPajak' => $jenis->Id,
                'Tarif' => $data['Tarif'],
                'PengaliDppPembilang' => $data['PengaliDppPembilang'],
                'PengaliDppPenyebut' => $data['PengaliDppPenyebut'],
                // Tanggal berlaku sengaja diisi tanggal penyiapan; peninjau wajib menyesuaikannya sebelum mengajukan.
                'BerlakuMulai' => now('Asia/Jakarta')->toDateString(),
                'Status' => StatusDataMaster::Draf,
                'NomorDasarHukum' => $data['NomorDasarHukum'],
            ]);
        }
    }

    /**
     * @return list<array{KodeJenisPajak: string, Tarif: string, PengaliDppPembilang: int, PengaliDppPenyebut: int, NomorDasarHukum: string}>
     */
    private static function BacaData(string $path): array
    {
        $isi = is_readable($path) ? file_get_contents($path) : false;
        $data = $isi === false ? null : json_decode($isi, true);

        if (! is_array($data) || ! isset($data['Tarif']) || ! is_array($data['Tarif'])) {
            throw new RuntimeException("File data tarif awal tidak valid: {$path}");
        }

        $hasil = [];

        foreach ($data['Tarif'] as $baris) {
            if (! is_array($baris) || ! is_string($baris['KodeJenisPajak'] ?? null) || ! is_string($baris['Tarif'] ?? null)
                || ! is_int($baris['PengaliDppPembilang'] ?? null) || ! is_int($baris['PengaliDppPenyebut'] ?? null)
                || ! is_string($baris['NomorDasarHukum'] ?? null)) {
                throw new RuntimeException("Baris tarif awal tidak lengkap di {$path}. Tarif wajib string desimal.");
            }

            $hasil[] = [
                'KodeJenisPajak' => $baris['KodeJenisPajak'],
                'Tarif' => $baris['Tarif'],
                'PengaliDppPembilang' => $baris['PengaliDppPembilang'],
                'PengaliDppPenyebut' => $baris['PengaliDppPenyebut'],
                'NomorDasarHukum' => $baris['NomorDasarHukum'],
            ];
        }

        return $hasil;
    }
}
