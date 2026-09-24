<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Persediaan;

use App\Domain\Organisasi\Data\DataInfoGudang;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Organisasi\Kueri\InfoGudang;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;

/**
 * Bantuan bersama kontroler persediaan F-05a (semua tim, DesainF05a D): lokasi stok lewat ULID publik di dalam
 * scope tenant aktif dan outlet yang boleh diakses pelaku (lainnya = 404), opsi lokasi stok untuk form/saringan
 * (tipe FE `OpsiGudang`), dan prop `Izin` (tipe FE `IzinPersediaan`).
 */
abstract class DasarPersediaanKontroler extends DasarKelolaKontroler
{
    /**
     * Lokasi stok (termasuk yang diarsipkan) yang boleh diakses pelaku. Lokasi tenant lain, di outlet di luar akses,
     * atau tanpa outlet saat akses pelaku dibatasi per outlet = 404.
     */
    protected function CariGudangBoleh(string $uuid): DataInfoGudang
    {
        $gudang = app(InfoGudang::class)->AmbilDariUuid([$uuid])[$uuid] ?? null;
        abort_if($gudang === null, 404);

        $boleh = $this->IdOutletBoleh();
        abort_if($boleh !== null && ($gudang->idOutlet === null || ! in_array($gudang->idOutlet, $boleh, true)), 404);

        return $gudang;
    }

    /**
     * Opsi lokasi stok di outlet yang boleh diakses pelaku (tipe FE `OpsiGudang[]`), urut outlet lalu nama.
     *
     * @return list<array{Uuid: string, Kode: string, Nama: string, Jenis: string, NamaOutlet: string|null, Aktif: bool}>
     */
    protected function AmbilOpsiGudang(bool $hanyaAktif = true): array
    {
        return array_map(fn (DataInfoGudang $g): array => [
            'Uuid' => $g->uuid,
            'Kode' => $g->kode,
            'Nama' => $g->nama,
            'Jenis' => $g->jenis->value,
            'NamaOutlet' => $g->namaOutlet,
            'Aktif' => $g->aktif,
        ], app(InfoGudang::class)->AmbilBoleh($this->IdOutletBoleh(), $hanyaAktif));
    }

    protected function CekIzin(IzinTenant $izin): bool
    {
        return app(AksesPengguna::class)->CekIzin($this->IdTenant(), $this->Pelaku()->Id, $izin);
    }

    /**
     * Prop `Izin` halaman persediaan (tipe FE `IzinPersediaan`). Hanya untuk tampilan; rute tetap dijaga
     * `WajibIzinTenant`.
     *
     * @return array{Lihat: bool, Kelola: bool, PostingStokAwal: bool, LihatJurnal: bool, UbahPengaturan: bool}
     */
    protected function AmbilIzinPersediaan(): array
    {
        return [
            'Lihat' => $this->CekIzin(IzinTenant::PersediaanLihat),
            'Kelola' => $this->CekIzin(IzinTenant::PersediaanKelola),
            'PostingStokAwal' => $this->CekIzin(IzinTenant::PersediaanStokAwalPosting),
            'LihatJurnal' => $this->CekIzin(IzinTenant::LaporanKeuanganLihat),
            'UbahPengaturan' => $this->CekIzin(IzinTenant::AkuntansiKelola),
        ];
    }
}
