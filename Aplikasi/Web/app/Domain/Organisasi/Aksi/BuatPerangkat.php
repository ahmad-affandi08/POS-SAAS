<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Enum\JenisPerangkat;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Kueri\PemakaianPerangkat;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Organisasi\Model\Perangkat;
use App\Domain\Tenant\Layanan\PastikanBatasPaket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * F-02 langkah 5: admin menambah perangkat di outlet lalu langsung mendapat kode aktivasi.
 * - BR-02.1: perangkat per outlet dibatasi paket (`BatasPerangkatPerOutlet`); perangkat dicabut tidak dihitung.
 * - Kode perangkat `{KodeOutlet}-{Huruf}{NN}` (misal `JKT1-K02`) untuk penomoran offline, tidak pernah dipakai ulang.
 * - BR-02.2: perangkat pertama mengunci kode outlet (`Outlet.KodeDikunciPada`), karena kode perangkat & nomor
 *   dokumen offline memuat kode outlet.
 */
final class BuatPerangkat
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PastikanBatasPaket $batasPaket,
        private readonly PemakaianPerangkat $pemakaian,
        private readonly BuatKodeAktivasi $buatKode,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @return array{Perangkat: Perangkat, Kode: string, KedaluwarsaPada: Carbon}
     */
    public function Jalankan(Outlet $outlet, string $nama, JenisPerangkat $jenis, ?int $idPenggunaPembuat): array
    {
        return DB::transaction(function () use ($outlet, $nama, $jenis, $idPenggunaPembuat): array {
            // Langganan dikunci lebih dulu (urutan kunci sama dengan penambahan outlet/pengguna).
            $this->batasPaket->Pastikan($this->konteks->Wajib(), 'BatasPerangkatPerOutlet', fn (): int => $this->pemakaian->HitungAktifDiOutlet($outlet->Id));
            $outlet = Outlet::query()->lockForUpdate()->findOrFail($outlet->Id);

            if ($outlet->Status !== StatusOrganisasi::Aktif) {
                throw new PelanggaranAturanBisnis('OutletDiarsipkan', 'Perangkat hanya bisa ditambahkan di outlet aktif. Pulihkan outlet ini dulu.', 'Outlet');
            }

            $perangkat = Perangkat::query()->create([
                'IdOutlet' => $outlet->Id,
                'Kode' => $this->TentukanKode($outlet->Kode, $jenis),
                'Nama' => trim($nama),
                'Jenis' => $jenis,
            ]);

            if ($outlet->KodeDikunciPada === null) {
                $outlet->KodeDikunciPada = now();
                $outlet->save();
                $this->audit->Catat('outlet.kunci-kode', $outlet, nilaiBaru: ['Kode' => $outlet->Kode, 'Alasan' => 'Perangkat pertama']);
            }

            $this->audit->Catat('perangkat.buat', $perangkat, nilaiBaru: $perangkat->only(['IdOutlet', 'Kode', 'Nama', 'Jenis']));
            $kode = $this->buatKode->Jalankan($perangkat, $idPenggunaPembuat);

            return ['Perangkat' => $perangkat, 'Kode' => $kode['Kode'], 'KedaluwarsaPada' => $kode['KedaluwarsaPada']];
        });
    }

    /** Nomor urut berikutnya per outlet & jenis, dihitung dari semua perangkat termasuk yang dicabut. */
    private function TentukanKode(string $kodeOutlet, JenisPerangkat $jenis): string
    {
        $awalan = "{$kodeOutlet}-{$jenis->AmbilHuruf()}";
        $nomorTerakhir = Perangkat::query()
            ->where('Kode', 'like', $awalan.'%')
            ->pluck('Kode')
            ->map(fn (mixed $kode): int => is_string($kode) && preg_match('/^'.preg_quote($awalan, '/').'(\d+)$/', $kode, $cocok) === 1 ? (int) $cocok[1] : 0)
            ->max() ?? 0;

        return $awalan.str_pad((string) ($nomorTerakhir + 1), 2, '0', STR_PAD_LEFT);
    }
}
