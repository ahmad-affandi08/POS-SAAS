<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\Integrasi\Enum\LingkunganIntegrasi;
use App\Domain\Pengelola\Integrasi\Enum\StatusIntegrasi;
use App\Domain\Pengelola\Integrasi\Layanan\PenerapKonfigurasiIntegrasi;
use App\Domain\Pengelola\Integrasi\Model\KonfigurasiIntegrasi;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use Illuminate\Support\Facades\DB;

/**
 * Mengaktifkan atau menonaktifkan konfigurasi (P-05 langkah 3). Aktif hanya bila tes koneksi terakhir berhasil
 * setelah perubahan terakhir (BR-P05.4). Lingkungan Produksi wajib alasan (BR-P05.2).
 */
final class UbahStatusIntegrasi
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(PenggunaPengelola $pelaku, KonfigurasiIntegrasi $konfigurasi, bool $aktif, ?string $alasan): KonfigurasiIntegrasi
    {
        $konfigurasi = DB::transaction(function () use ($pelaku, $konfigurasi, $aktif, $alasan): KonfigurasiIntegrasi {
            $konfigurasi = KonfigurasiIntegrasi::query()->lockForUpdate()->findOrFail($konfigurasi->Id);

            if ($konfigurasi->Lingkungan === LingkunganIntegrasi::Produksi && ($alasan === null || trim($alasan) === '')) {
                throw new PelanggaranAturanBisnis('BR-P05.2', 'Tulis alasan perubahan konfigurasi produksi.', 'Alasan');
            }

            if ($aktif && $konfigurasi->Status !== StatusIntegrasi::Terhubung) {
                throw new PelanggaranAturanBisnis('BR-P05.4', 'Uji koneksi sampai berhasil sebelum mengaktifkan integrasi ini.');
            }

            if ($konfigurasi->Aktif === $aktif) {
                throw new PelanggaranAturanBisnis('StatusTidakSesuai', $aktif ? 'Integrasi ini sudah aktif.' : 'Integrasi ini sudah nonaktif.');
            }

            $konfigurasi->update(['Aktif' => $aktif]);
            $this->audit->Catat(
                $aktif ? 'integrasi.aktifkan' : 'integrasi.nonaktifkan',
                $konfigurasi,
                nilaiLama: ['Aktif' => ! $aktif],
                nilaiBaru: ['Aktif' => $aktif, 'Jenis' => $konfigurasi->Jenis->value, 'Lingkungan' => $konfigurasi->Lingkungan->value],
                alasan: $alasan,
                idPelaku: $pelaku->Id,
            );

            return $konfigurasi;
        });

        PenerapKonfigurasiIntegrasi::LupakanCache();

        return $konfigurasi;
    }
}
