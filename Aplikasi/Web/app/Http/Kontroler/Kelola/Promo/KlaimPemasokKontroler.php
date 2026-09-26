<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Promo;

use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Promo\Aksi\TerimaKlaimPemasok;
use App\Domain\Promo\Enum\CaraPenerimaanKlaim;
use App\Domain\Promo\Kueri\DaftarKlaimPemasok;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Klaim promo ke pemasok (F-16c bagian 4b): klaim terbuka per pemasok dan riwayat penerimaan (lihat `pelanggan.lihat`);
 * catat penyelesaian klaim ke kas/bank (J-16.5) atau potong hutang ke pemasok (bagian 4e), izin `akuntansi.kelola`.
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
            'Cara' => ['required', Rule::enum(CaraPenerimaanKlaim::class)],
            'UuidAkunKasBank' => ['required_if:Cara,KasBank', 'nullable', 'string', 'ulid'],
            'Keterangan' => ['nullable', 'string', 'max:255'],
        ], attributes: ['UuidAkunKasBank' => 'akun kas/bank', 'Tanggal' => 'tanggal', 'Cara' => 'cara penyelesaian']);

        $penerimaan = $terima->Jalankan(
            strtoupper((string) $data['UuidPemasok']),
            CarbonImmutable::createFromFormat('Y-m-d', (string) $data['Tanggal']) ?: CarbonImmutable::now(),
            CaraPenerimaanKlaim::from((string) $data['Cara']),
            isset($data['UuidAkunKasBank']) ? strtoupper((string) $data['UuidAkunKasBank']) : null,
            isset($data['Keterangan']) && trim((string) $data['Keterangan']) !== '' ? trim((string) $data['Keterangan']) : null,
            $this->Pelaku()->Id,
        );

        $pesan = $penerimaan->Cara === CaraPenerimaanKlaim::PotongHutang ? ' dipotong dari hutang ke pemasok.' : ' dicatat.';

        return to_route('kelola.promo.klaim-pemasok')->with('Kilat', 'Penerimaan klaim '.Uang::Dari((string) $penerimaan->Jumlah)->FormatRupiah().$pesan);
    }
}
