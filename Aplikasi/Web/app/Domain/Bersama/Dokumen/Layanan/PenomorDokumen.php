<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Dokumen\Layanan;

use App\Domain\Bersama\Dokumen\Enum\JenisDokumenBernomor;
use App\Domain\Bersama\Dokumen\Model\NomorUrutDokumen;
use App\Domain\Bersama\Tenant\KonteksTenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Nomor urut dokumen tanpa celah per tenant, jenis, periode `YYYY-MM` (atau harian `YYYY-MM-DD`, F-17), dan (opsional) outlet/perangkat
 * (DesainF05a C.1). Dipanggil di dalam transaksi dokumennya, sebagai kunci terakhir (L7) sebelum insert: nomor yang
 * diambil ikut batal bila transaksi batal, sehingga tidak ada celah.
 *
 * Baris penghitung dibuat lewat upsert (INSERT … ON DUPLICATE KEY UPDATE) yang langsung mengambil kunci X, lalu
 * dibaca `FOR UPDATE` dan dinaikkan. Pola ini menghindari deadlock peningkatan kunci S→X saat dua transaksi
 * membuat baris periode baru bersamaan.
 */
final class PenomorDokumen
{
    public function __construct(private readonly KonteksTenant $konteks) {}

    /**
     * @param  string  $periode  `YYYY-MM`
     *
     * @throws InvalidArgumentException bila periode tidak berformat `YYYY-MM`
     */
    public function AmbilBerikutnya(JenisDokumenBernomor $jenis, string $periode, ?int $idOutlet = null, ?int $idPerangkat = null): int
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode) !== 1) {
            throw new InvalidArgumentException("Periode {$periode} harus berformat YYYY-MM.");
        }

        return $this->Naikkan($jenis, $periode, $idOutlet, $idPerangkat);
    }

    /**
     * F-17: nomor urut per hari (periode `YYYY-MM-DD`), misal pesanan QR meja per outlet per tanggal lokal outlet.
     *
     * @param  string  $tanggal  `YYYY-MM-DD`
     *
     * @throws InvalidArgumentException bila tanggal tidak berformat `YYYY-MM-DD` yang sah
     */
    public function AmbilBerikutnyaHarian(JenisDokumenBernomor $jenis, string $tanggal, ?int $idOutlet = null, ?int $idPerangkat = null): int
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $tanggal, $cocok) !== 1 || ! checkdate((int) $cocok[2], (int) $cocok[3], (int) $cocok[1])) {
            throw new InvalidArgumentException("Tanggal {$tanggal} harus berformat YYYY-MM-DD.");
        }

        return $this->Naikkan($jenis, $tanggal, $idOutlet, $idPerangkat);
    }

    private function Naikkan(JenisDokumenBernomor $jenis, string $periode, ?int $idOutlet, ?int $idPerangkat): int
    {
        $idTenant = $this->konteks->Wajib();

        return DB::transaction(function () use ($idTenant, $jenis, $periode, $idOutlet, $idPerangkat): int {
            NomorUrutDokumen::query()->upsert(
                [[
                    'IdTenant' => $idTenant,
                    'IdOutlet' => $idOutlet,
                    'IdPerangkat' => $idPerangkat,
                    'JenisDokumen' => $jenis->value,
                    'Periode' => $periode,
                    'NomorTerakhir' => 0,
                ]],
                ['IdTenant', 'JenisDokumen', 'Periode', 'KunciOutlet', 'KunciPerangkat'],
                ['DiubahPada'],
            );

            $penghitung = NomorUrutDokumen::query()
                ->where('JenisDokumen', $jenis->value)
                ->where('Periode', $periode)
                ->where('KunciOutlet', $idOutlet ?? 0)
                ->where('KunciPerangkat', $idPerangkat ?? 0)
                ->lockForUpdate()
                ->firstOrFail();

            $penghitung->NomorTerakhir++;
            $penghitung->save();

            return $penghitung->NomorTerakhir;
        });
    }

    /** Nomor berikutnya yang sudah diformat (misal `SA/2026/09/0001`). */
    public function AmbilNomorBerikutnya(JenisDokumenBernomor $jenis, string $periode, ?int $idOutlet = null, ?int $idPerangkat = null): string
    {
        return $jenis->FormatNomor($periode, $this->AmbilBerikutnya($jenis, $periode, $idOutlet, $idPerangkat));
    }
}
