<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Persediaan;

use App\Domain\Persediaan\Kueri\DaftarSaldoStok;
use App\Domain\Tenant\Kueri\PengaturanPersediaanTenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman saldo stok, izin `persediaan.lihat` (DesainF05a D; tipe FE `PropsSaldoStok`). Query: `kata`, `gudang`
 * (Uuid lokasi stok), `keadaan` (Semua/Ada/Nol/Minus), `urut` (Nama/-Nilai/Jumlah), `halaman`. Hanya lokasi stok di
 * outlet yang boleh diakses pelaku; lokasi yang diarsipkan ikut (berlabel di FE).
 */
final class SaldoStokKontroler extends DasarPersediaanKontroler
{
    public function Daftar(Request $permintaan, DaftarSaldoStok $daftar, PengaturanPersediaanTenant $pengaturan): Response
    {
        $saring = DaftarSaldoStok::NormalkanSaring(
            $permintaan->query('kata'),
            $permintaan->query('gudang'),
            $permintaan->query('keadaan'),
            $permintaan->query('urut'),
        );
        $halaman = $permintaan->query('halaman');

        return Inertia::render('Kelola/Persediaan/Saldo', [
            ...$daftar->Ambil($saring, $this->IdOutletBoleh(), is_numeric($halaman) ? (int) $halaman : 1),
            'Saring' => $saring,
            'OpsiGudang' => $this->AmbilOpsiGudang(false),
            'MetodeHpp' => $pengaturan->Ambil()->metodeHpp->value,
        ]);
    }
}
