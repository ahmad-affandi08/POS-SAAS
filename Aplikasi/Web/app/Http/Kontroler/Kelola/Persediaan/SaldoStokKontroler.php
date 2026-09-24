<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Persediaan;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Persediaan\Kueri\DaftarSaldoStok;
use App\Domain\Tenant\Kueri\PengaturanPersediaanTenant;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Halaman saldo stok, izin `persediaan.lihat` (DesainF05a D; tipe FE `PropsSaldoStok`). Query: `kata`, `gudang`
 * (Uuid lokasi stok), `keadaan` (Semua/Ada/Nol/Minus), `urut` (Nama/-Nilai/Jumlah), `halaman`. Hanya lokasi stok di
 * outlet yang boleh diakses pelaku; lokasi yang diarsipkan ikut (berlabel di FE).
 */
final class SaldoStokKontroler extends DasarPersediaanKontroler
{
    public function Daftar(Request $permintaan, DaftarSaldoStok $daftar, PengaturanPersediaanTenant $pengaturan): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarSaldoStok::KOLOM_URUT, 'Nama', DaftarSaldoStok::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Persediaan/Saldo', 'Saldo', fn (): array => $daftar->AmbilTabel($tabel, $this->IdOutletBoleh()), fn (): array => [
            'OpsiGudang' => $this->AmbilOpsiGudang(false),
            'MetodeHpp' => $pengaturan->Ambil()->metodeHpp->value,
        ]);
    }
}
