<?php

declare(strict_types=1);

namespace App\Domain\Akuntansi\Layanan;

use App\Domain\Akuntansi\Enum\PeranAkun;
use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Akuntansi\Model\PemetaanAkun;
use App\Domain\Bersama\Audit\Layanan\PencatatAudit;

/**
 * Layanan publik Akuntansi: peran akun yang ditambahkan setelah tenant menerapkan template sektor (misal
 * `PiutangKlaimPemasok`, F-16c bagian 4d) dipastikan punya akun & pemetaan tingkat tenant sebelum dipakai jurnal.
 * Hanya bila peran belum dipetakan sama sekali: akun dengan kode bawaan dipakai bila ada dan bertipe sesuai, selain itu
 * akun sistem baru dibuat. Pemetaan yang sudah ada (termasuk yang salah tipe) tidak disentuh; `PenentuAkun` tetap
 * menolaknya dengan `PemetaanAkunBelumAda`. Audit `akun.tambah-template`.
 */
final class PenyediaAkunPeran
{
    public function __construct(
        private readonly PenentuAkun $penentu,
        private readonly PencatatAudit $audit,
    ) {}

    public function Pastikan(PeranAkun $peran, string $kode, string $nama, ?int $idOutlet = null): int
    {
        $ada = $this->penentu->CariIdAkun($peran, $idOutlet);

        if ($ada !== null) {
            return $ada;
        }

        if (PemetaanAkun::query()->where('Kunci', $peran->value)->exists()) {
            return $this->penentu->AmbilIdAkun($peran, $idOutlet);
        }

        $tipe = $peran->AmbilTipeAkun();
        $akun = Akun::query()->where('Kode', $kode)->first();
        $kodeBaru = [];

        if (! $akun instanceof Akun || $akun->Jenis !== $tipe) {
            $akun = Akun::query()->create([
                'Kode' => $akun instanceof Akun ? $this->CariKodeKosong($kode) : $kode,
                'Nama' => $nama,
                'Jenis' => $tipe,
                'SaldoNormal' => $tipe->AmbilSaldoNormal(),
                'Sistem' => true,
            ]);
            $kodeBaru[] = $akun->Kode;
        }

        PemetaanAkun::query()->create(['Kunci' => $peran->value, 'IdAkun' => $akun->Id, 'IdOutlet' => null]);
        $this->audit->Catat('akun.tambah-template', nilaiBaru: ['Kode' => $kodeBaru, 'Pemetaan' => [$peran->value]]);

        return $akun->Id;
    }

    /** Kode bawaan dipakai akun lain bertipe berbeda: nomor berikutnya yang kosong (1-1460 → 1-1461, ...). */
    private function CariKodeKosong(string $kode): string
    {
        [$awalan, $nomor] = explode('-', $kode, 2) + [1 => '0'];
        $n = (int) $nomor;

        do {
            $n++;
            $calon = $awalan.'-'.str_pad((string) $n, strlen($nomor), '0', STR_PAD_LEFT);
        } while (Akun::query()->where('Kode', $calon)->exists());

        return $calon;
    }
}
