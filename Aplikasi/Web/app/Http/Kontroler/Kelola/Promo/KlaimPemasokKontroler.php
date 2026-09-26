<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Promo;

use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Promo\Aksi\TerimaKlaimPemasok;
use App\Domain\Promo\Kueri\DaftarKlaimPemasok;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Klaim promo ke pemasok (F-16c bagian 4b): klaim terbuka per pemasok dan riwayat penerimaan (lihat `pelanggan.lihat`);
 * catat penerimaan pembayaran klaim ke kas/bank (J-16.5, izin `akuntansi.kelola`).
 */
final class KlaimPemasokKontroler extends DasarKelolaKontroler
{
    public function Daftar(DaftarKlaimPemasok $daftar, DaftarAkunPilihan $akun, AksesPengguna $akses): Response
    {
        return Inertia::render('Kelola/Promo/KlaimPemasok', [
            'Terbuka' => $daftar->AmbilTerbuka(),
            'Penerimaan' => $daftar->AmbilPenerimaan(),
            'OpsiAkunKasBank' => array_map(fn (array $a): array => ['Uuid' => $a['Uuid'], 'Nama' => $a['Kode'].' '.$a['Nama']], $akun->AmbilKasBank()),
            'Izin' => ['Terima' => $akses->CekIzin($this->IdTenant(), $this->Pelaku()->Id, IzinTenant::AkuntansiKelola)],
        ]);
    }

    public function Terima(Request $permintaan, TerimaKlaimPemasok $terima): RedirectResponse
    {
        $data = $permintaan->validate([
            'UuidPemasok' => ['required', 'string', 'ulid'],
            'Tanggal' => ['required', 'date_format:Y-m-d'],
            'UuidAkunKasBank' => ['required', 'string', 'ulid'],
            'Keterangan' => ['nullable', 'string', 'max:255'],
        ], attributes: ['UuidAkunKasBank' => 'akun kas/bank', 'Tanggal' => 'tanggal']);

        $penerimaan = $terima->Jalankan(
            strtoupper((string) $data['UuidPemasok']),
            CarbonImmutable::createFromFormat('Y-m-d', (string) $data['Tanggal']) ?: CarbonImmutable::now(),
            strtoupper((string) $data['UuidAkunKasBank']),
            isset($data['Keterangan']) && trim((string) $data['Keterangan']) !== '' ? trim((string) $data['Keterangan']) : null,
            $this->Pelaku()->Id,
        );

        return to_route('kelola.promo.klaim-pemasok')->with('Kilat', 'Penerimaan klaim '.Uang::Dari((string) $penerimaan->Jumlah)->FormatRupiah().' dicatat.');
    }
}
