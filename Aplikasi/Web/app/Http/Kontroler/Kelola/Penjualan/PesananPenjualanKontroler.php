<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Penjualan;

use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Penjualan\Aksi\SelesaikanUangMukaPesanan;
use App\Domain\Penjualan\Aksi\TandaiPesananPenjualanSiap;
use App\Domain\Penjualan\Enum\CaraPenyelesaianUangMuka;
use App\Domain\Penjualan\Enum\StatusPesananPenjualan;
use App\Domain\Penjualan\Kueri\DaftarPesananPenjualan;
use App\Domain\Penjualan\Model\PesananPenjualan;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pre-order back-office (F-12 bagian 2, `/kelola/pre-order`): daftar (`TabelData` mode server) & detail (lihat:
 * `laporan.penjualan.lihat`), tandai siap (`penjualan.buat`), batalkan / selesaikan sisa uang muka dengan dikembalikan
 * atau hangus (`akuntansi.kelola`). Pre-order tenant lain atau outlet di luar akses = 404.
 */
final class PesananPenjualanKontroler extends DasarKelolaKontroler
{
    public function Daftar(Request $permintaan, DaftarPesananPenjualan $daftar): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarPesananPenjualan::KOLOM_URUT, DaftarPesananPenjualan::URUT_BAWAAN, DaftarPesananPenjualan::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/PreOrder/Daftar', 'Pesanan', fn (): array => $daftar->AmbilTabel($tabel, $this->IdOutletBoleh()), fn (): array => [
            'OpsiStatus' => array_map(fn (StatusPesananPenjualan $s): array => ['Nilai' => $s->value, 'Label' => $s->AmbilLabel()], StatusPesananPenjualan::cases()),
        ]);
    }

    public function Detail(string $pesananPenjualan, DaftarPesananPenjualan $daftar, DaftarAkunPilihan $akun): Response
    {
        $izin = app(AksesPengguna::class);

        return Inertia::render('Kelola/PreOrder/Detail', [
            'Pesanan' => $daftar->AmbilDetail($this->Cari($pesananPenjualan)),
            'OpsiAkun' => array_map(fn (array $a): array => ['Uuid' => $a['Uuid'], 'Kode' => $a['Kode'], 'Nama' => $a['Nama']], $akun->AmbilKasBank()),
            'OpsiCara' => array_map(fn (CaraPenyelesaianUangMuka $c): array => ['Nilai' => $c->value, 'Label' => $c->AmbilLabel()], CaraPenyelesaianUangMuka::cases()),
            'Izin' => [
                'Siap' => $izin->CekIzin($this->IdTenant(), $this->Pelaku()->Id, IzinTenant::PenjualanBuat),
                'Selesaikan' => $izin->CekIzin($this->IdTenant(), $this->Pelaku()->Id, IzinTenant::AkuntansiKelola),
            ],
        ]);
    }

    public function Siap(string $pesananPenjualan, TandaiPesananPenjualanSiap $tandai): RedirectResponse
    {
        $hasil = $tandai->Jalankan($this->Cari($pesananPenjualan), $this->Pelaku()->Id);

        return back()->with('Kilat', "{$hasil->Nomor} siap diambil.");
    }

    public function Selesaikan(Request $permintaan, string $pesananPenjualan, SelesaikanUangMukaPesanan $selesaikan): RedirectResponse
    {
        $valid = $permintaan->validate([
            'Cara' => ['required', Rule::enum(CaraPenyelesaianUangMuka::class)],
            'UuidAkun' => ['nullable', 'string', 'ulid'],
            'Alasan' => ['required', 'string', 'min:5', 'max:255'],
        ], attributes: ['UuidAkun' => 'akun kas/bank', 'Alasan' => 'alasan']);
        $hasil = $selesaikan->Jalankan(
            $this->Cari($pesananPenjualan),
            CaraPenyelesaianUangMuka::from((string) $valid['Cara']),
            isset($valid['UuidAkun']) ? strtoupper((string) $valid['UuidAkun']) : null,
            (string) $valid['Alasan'],
            $this->Pelaku()->Id,
        );

        return back()->with('Kilat', $hasil->Status === StatusPesananPenjualan::Dibatalkan ? "{$hasil->Nomor} dibatalkan." : "Sisa uang muka {$hasil->Nomor} diselesaikan.");
    }

    private function Cari(string $uuid): PesananPenjualan
    {
        $pesanan = PesananPenjualan::query()->where('Uuid', $uuid)->firstOrFail();
        $boleh = $this->IdOutletBoleh();
        abort_if($boleh !== null && ! in_array($pesanan->IdOutlet, $boleh, true), 404);

        return $pesanan;
    }
}
