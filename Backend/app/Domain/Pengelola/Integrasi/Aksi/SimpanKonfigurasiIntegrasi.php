<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Integrasi\Data\DataKonfigurasiIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\LingkunganIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\StatusIntegrasi;
use App\Domain\Pengelola\Integrasi\Layanan\PenerapKonfigurasiIntegrasi;
use App\Domain\Pengelola\Integrasi\Model\KonfigurasiIntegrasi;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Menyimpan konfigurasi integrasi (P-05 langkah 1). Kredensial langsung terenkripsi; hanya petunjuk 4 karakter
 * terakhir yang disimpan terbuka (BR-P05.1). Perubahan pengaturan/kredensial mengembalikan status ke BelumDiuji dan
 * menonaktifkan konfigurasi sampai diuji ulang (BR-P05.4). Log audit hanya memuat nama kolom kredensial (BR-P05.6).
 */
final class SimpanKonfigurasiIntegrasi
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, DataKonfigurasiIntegrasi $data): KonfigurasiIntegrasi
    {
        if ($data->lingkungan === LingkunganIntegrasi::Produksi && ($data->alasan === null || trim($data->alasan) === '')) {
            throw new PelanggaranAturanBisnis('BR-P05.2', 'Tulis alasan perubahan konfigurasi produksi.', 'Alasan');
        }

        try {
            $konfigurasi = DB::transaction(fn (): KonfigurasiIntegrasi => $this->Simpan($pelaku, $data));
        } catch (UniqueConstraintViolationException) {
            throw new PelanggaranAturanBisnis('SudahAda', 'Konfigurasi ini baru saja dibuat anggota lain. Muat ulang halaman.');
        }

        PenerapKonfigurasiIntegrasi::LupakanCache();

        return $konfigurasi;
    }

    private function Simpan(PenggunaPengelola $pelaku, DataKonfigurasiIntegrasi $data): KonfigurasiIntegrasi
    {
        $penyedia = $data->jenis->AmbilPenyedia();
        $konfigurasi = KonfigurasiIntegrasi::query()
            ->where('Jenis', $data->jenis->value)
            ->where('Lingkungan', $data->lingkungan->value)
            ->lockForUpdate()
            ->first();

        $kredensialLama = $konfigurasi === null ? [] : $konfigurasi->Kredensial;
        $kredensialBaru = [];
        $kredensialBerubah = [];

        foreach ($penyedia->AmbilBidangKredensial() as $bidang) {
            $kunci = $bidang['Kunci'];
            $nilai = $data->kredensial[$kunci] ?? '';

            if ($nilai === '') {
                if (! isset($kredensialLama[$kunci])) {
                    throw new PelanggaranAturanBisnis('KredensialWajib', "{$bidang['Label']} wajib diisi.", "Kredensial.{$kunci}");
                }

                $kredensialBaru[$kunci] = $kredensialLama[$kunci];

                continue;
            }

            $kredensialBaru[$kunci] = $nilai;

            if (($kredensialLama[$kunci] ?? null) !== $nilai) {
                $kredensialBerubah[] = $kunci;
            }
        }

        $pengaturanLama = $konfigurasi?->Pengaturan;
        $pengaturanBerubah = $pengaturanLama !== $data->pengaturan;
        $isiBerubah = $konfigurasi === null || $pengaturanBerubah || $kredensialBerubah !== [];
        $nilaiLama = $konfigurasi === null ? null : self::AmbilRingkasan($konfigurasi);

        $konfigurasi ??= new KonfigurasiIntegrasi([
            'Jenis' => $data->jenis,
            'Lingkungan' => $data->lingkungan,
            'Penyedia' => $penyedia,
        ]);
        $konfigurasi->fill([
            'Pengaturan' => $data->pengaturan,
            'Kredensial' => $kredensialBaru,
            'PetunjukKredensial' => array_map(self::BuatPetunjuk(...), $kredensialBaru),
            'RotasiSetiapHari' => $data->rotasiSetiapHari,
        ]);

        if ($kredensialBerubah !== []) {
            $konfigurasi->KredensialDiubahPada = now();
        }

        if ($isiBerubah) {
            // BR-P05.4: sistem tidak memakai isian yang belum terbukti tersambung.
            $konfigurasi->fill(['Status' => StatusIntegrasi::BelumDiuji, 'Aktif' => false, 'GagalBeruntun' => 0]);
        }

        $konfigurasi->save();

        $this->audit->Catat(
            $nilaiLama === null ? 'integrasi.buat' : 'integrasi.ubah',
            $konfigurasi,
            nilaiLama: $nilaiLama,
            nilaiBaru: [...self::AmbilRingkasan($konfigurasi), 'KredensialDiganti' => $kredensialBerubah],
            alasan: $data->alasan,
            idPelaku: $pelaku->Id,
        );

        return $konfigurasi;
    }

    /** BR-P05.1: hanya 4 karakter terakhir; nilai pendek tidak ditampilkan sama sekali. */
    public static function BuatPetunjuk(string $nilai): string
    {
        return mb_strlen($nilai) >= 12 ? '••••'.mb_substr($nilai, -4) : '••••';
    }

    /**
     * @return array<string, mixed>
     */
    private static function AmbilRingkasan(KonfigurasiIntegrasi $konfigurasi): array
    {
        return [
            'Jenis' => $konfigurasi->Jenis->value,
            'Lingkungan' => $konfigurasi->Lingkungan->value,
            'Pengaturan' => $konfigurasi->Pengaturan,
            'Aktif' => $konfigurasi->Aktif,
            'RotasiSetiapHari' => $konfigurasi->RotasiSetiapHari,
        ];
    }
}
