<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\Organisasi\Aksi\HapusPeran;
use App\Domain\Organisasi\Aksi\SimpanPeran;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Enum\StatusKeanggotaan;
use App\Domain\Organisasi\Model\Peran;
use App\Domain\Organisasi\Model\TenantPengguna;
use App\Http\Permintaan\Kelola\SimpanPeranPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Peran & izin tenant (PRD §19.1): peran bawaan hanya dibaca, peran kustom dibuat/diubah/dihapus.
 */
final class PeranKontroler extends DasarKelolaKontroler
{
    public function Daftar(): Response
    {
        $jumlahAnggota = TenantPengguna::query()
            ->where('IdTenant', $this->IdTenant())
            ->where('Status', StatusKeanggotaan::Aktif->value)
            ->selectRaw('IdPeran, count(*) as Jumlah')
            ->groupBy('IdPeran')
            ->pluck('Jumlah', 'IdPeran');

        return Inertia::render('Kelola/Peran/Daftar', [
            'Peran' => Peran::query()->with('Izin')->orderByDesc('Bawaan')->orderBy('Id')->get()->map(fn (Peran $peran): array => [
                'Uuid' => $peran->Uuid,
                'Nama' => $peran->Nama,
                'Keterangan' => $peran->Keterangan,
                'Bawaan' => $peran->Bawaan,
                'Pemilik' => $peran->CekPemilik(),
                'Izin' => $peran->CekPemilik() ? IzinTenant::AmbilSemuaKunci() : $peran->AmbilKunciIzin(),
                'JumlahAnggota' => (int) ($jumlahAnggota->get($peran->Id) ?? 0),
            ])->values(),
            'DaftarIzin' => self::AmbilDaftarIzin(),
        ]);
    }

    /** Halaman penuh "Buat peran" (pola sama dengan Tambah produk). */
    public function Buat(): Response
    {
        return Inertia::render('Kelola/Peran/Buat', [
            'DaftarIzin' => self::AmbilDaftarIzin(),
        ]);
    }

    public function Simpan(SimpanPeranPermintaan $permintaan, SimpanPeran $simpan): RedirectResponse
    {
        $peran = $simpan->Jalankan($this->Pelaku()->Id, null, $permintaan->string('Nama')->toString(), $this->AmbilKeterangan($permintaan), $permintaan->AmbilIzin());

        return redirect()->route('kelola.peran.daftar')->with('Kilat', "Peran {$peran->Nama} dibuat.");
    }

    public function Ubah(string $peran, SimpanPeranPermintaan $permintaan, SimpanPeran $simpan): RedirectResponse
    {
        $baris = $simpan->Jalankan($this->Pelaku()->Id, $this->CariPeran($peran), $permintaan->string('Nama')->toString(), $this->AmbilKeterangan($permintaan), $permintaan->AmbilIzin());

        return back()->with('Kilat', "Peran {$baris->Nama} disimpan. Izin baru berlaku di permintaan berikutnya anggotanya.");
    }

    public function Hapus(string $peran, HapusPeran $hapus): RedirectResponse
    {
        $baris = $this->CariPeran($peran);
        $hapus->Jalankan($baris);

        return back()->with('Kilat', "Peran {$baris->Nama} dihapus.");
    }

    /**
     * @return list<array{Kunci: string, Label: string, Kelompok: string, KhususPemilik: bool}>
     */
    private static function AmbilDaftarIzin(): array
    {
        return array_map(fn (IzinTenant $izin): array => [
            'Kunci' => $izin->value,
            'Label' => $izin->AmbilLabel(),
            'Kelompok' => $izin->AmbilKelompok(),
            'KhususPemilik' => $izin->CekKhususPemilik(),
        ], IzinTenant::cases());
    }

    private function CariPeran(string $uuid): Peran
    {
        return Peran::query()->where('Uuid', $uuid)->firstOrFail();
    }

    private function AmbilKeterangan(SimpanPeranPermintaan $permintaan): ?string
    {
        return $permintaan->filled('Keterangan') ? $permintaan->string('Keterangan')->toString() : null;
    }
}
