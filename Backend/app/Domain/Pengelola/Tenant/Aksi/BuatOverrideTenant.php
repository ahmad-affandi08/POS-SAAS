<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Tenant\Aksi;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\JenisOverride;
use App\Domain\Tenant\Model\Fitur;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\OverrideTenant;
use App\Domain\Tenant\Model\Paket;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Override batas/fitur sementara (P-07, BR-P07.7). Tanggal berakhir wajib (paling lama 90 hari) dan alasan wajib;
 * setelah lewat, override otomatis diabaikan EvaluatorFitur. Satu kunci hanya boleh punya satu override aktif:
 * untuk mengubahnya, cabut dulu yang lama.
 *
 * - Batas: kunci salah satu `Paket::KOLOM_BATAS`, nilai bilangan bulat ≥ 0 (menimpa batas paket + add-on).
 * - Fitur: kunci fitur yang ada di katalog (P-04); memberi fitur di luar paket.
 */
final class BuatOverrideTenant
{
    public const MAKS_HARI = 90;

    public const NILAI_MAKS_BATAS = 1000000;

    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    public function Jalankan(
        PenggunaPengelola $pelaku,
        Tenant $tenant,
        JenisOverride $jenis,
        string $kunci,
        ?int $nilai,
        Carbon $berakhirPada,
        string $alasan,
    ): OverrideTenant {
        $nilaiSimpan = $this->ValidasiIsi($jenis, $kunci, $nilai);

        if (! $berakhirPada->isFuture()) {
            throw new PelanggaranAturanBisnis('BR-P07.7', 'Tanggal berakhir harus setelah sekarang.', 'BerakhirPada');
        }

        if ($berakhirPada->gt(now()->addDays(self::MAKS_HARI))) {
            throw new PelanggaranAturanBisnis('BR-P07.7', 'Override sementara paling lama '.self::MAKS_HARI.' hari.', 'BerakhirPada');
        }

        return DB::transaction(function () use ($pelaku, $tenant, $jenis, $kunci, $nilaiSimpan, $berakhirPada, $alasan): OverrideTenant {
            // Serialisasi per tenant lewat baris langganan agar dua override kunci sama tidak lolos bersamaan.
            Langganan::query()->where('IdTenant', $tenant->Id)->lockForUpdate()->first();

            $aktif = OverrideTenant::query()
                ->where('IdTenant', $tenant->Id)
                ->where('Jenis', $jenis->value)
                ->where('Kunci', $kunci)
                ->where('BerakhirPada', '>', now())
                ->exists();

            if ($aktif) {
                throw new PelanggaranAturanBisnis('BR-P07.7', "Override {$kunci} masih aktif. Cabut dulu sebelum membuat yang baru.", 'Kunci');
            }

            $override = OverrideTenant::query()->create([
                'IdTenant' => $tenant->Id,
                'Jenis' => $jenis,
                'Kunci' => $kunci,
                'Nilai' => $nilaiSimpan,
                'BerakhirPada' => $berakhirPada,
                'Alasan' => $alasan,
                'DibuatOleh' => $pelaku->Id,
            ]);

            $this->audit->Catat(
                'tenant.override.buat',
                $override,
                nilaiBaru: [
                    'Jenis' => $jenis->value,
                    'Kunci' => $kunci,
                    'Nilai' => $nilaiSimpan,
                    'BerakhirPada' => $berakhirPada->toIso8601String(),
                ],
                alasan: $alasan,
                idPelaku: $pelaku->Id,
                idTenant: $tenant->Id,
            );

            return $override;
        });
    }

    private function ValidasiIsi(JenisOverride $jenis, string $kunci, ?int $nilai): string
    {
        return match ($jenis) {
            JenisOverride::Batas => $this->ValidasiBatas($kunci, $nilai),
            JenisOverride::Fitur => Fitur::query()->where('Kunci', $kunci)->exists()
                ? 'Aktif'
                : throw new PelanggaranAturanBisnis('BR-P07.7', 'Fitur tidak ada di katalog.', 'Kunci'),
            JenisOverride::Trial => throw new PelanggaranAturanBisnis('BR-P07.6', 'Perpanjangan trial memakai tindakan Perpanjang trial.', 'Jenis'),
        };
    }

    private function ValidasiBatas(string $kunci, ?int $nilai): string
    {
        if (! in_array($kunci, Paket::KOLOM_BATAS, true)) {
            throw new PelanggaranAturanBisnis('BR-P07.7', 'Jenis batas tidak dikenal.', 'Kunci');
        }

        if ($nilai === null || $nilai < 0 || $nilai > self::NILAI_MAKS_BATAS) {
            throw new PelanggaranAturanBisnis('BR-P07.7', 'Isi batas berupa angka 0 sampai 1.000.000.', 'Nilai');
        }

        return (string) $nilai;
    }
}
