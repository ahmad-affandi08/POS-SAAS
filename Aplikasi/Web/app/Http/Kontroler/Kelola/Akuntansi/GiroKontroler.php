<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Akuntansi;

use App\Domain\Akuntansi\Aksi\CairkanGiro;
use App\Domain\Akuntansi\Aksi\TolakGiro;
use App\Domain\Akuntansi\Enum\ArahGiro;
use App\Domain\Akuntansi\Enum\StatusGiro;
use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Akuntansi\Kueri\DaftarGiro;
use App\Domain\Akuntansi\Model\Giro;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Http\Respons\ResponsTabel;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Giro/cek mundur (v3.42, F-12, `/kelola/akuntansi/giro`): daftar (lihat `laporan.keuangan.lihat`), catat cair ke bank
 * dan tolak (`akuntansi.kelola`). Giro dibuat dari pelunasan piutang atau pembayaran hutang.
 */
final class GiroKontroler extends DasarAkuntansiKontroler
{
    public function Daftar(Request $permintaan, DaftarGiro $daftar, DaftarAkunPilihan $akun): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarGiro::KOLOM_URUT, DaftarGiro::URUT_BAWAAN, DaftarGiro::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Akuntansi/Giro/Daftar', 'Giro', fn (): array => $daftar->Ambil($tabel), fn (): array => [
            'OpsiStatus' => array_map(fn (StatusGiro $s): array => ['Nilai' => $s->value, 'Label' => $s->AmbilLabel()], StatusGiro::cases()),
            'OpsiArah' => array_map(fn (ArahGiro $a): array => ['Nilai' => $a->value, 'Label' => $a->AmbilLabel()], ArahGiro::cases()),
            'OpsiAkun' => array_map(fn (array $a): array => ['Uuid' => $a['Uuid'], 'Kode' => $a['Kode'], 'Nama' => $a['Nama']], $akun->AmbilKasBank()),
            'HariIni' => app(TanggalBisnisOutlet::class)->Hitung(null)->toDateString(),
            'Izin' => ['Kelola' => $this->CekIzinKelola()],
        ]);
    }

    public function Cairkan(string $giro, Request $permintaan, CairkanGiro $cairkan): RedirectResponse
    {
        $data = $permintaan->validate([
            'UuidAkun' => ['required', 'string', 'size:26'],
            'Tanggal' => ['required', 'date_format:Y-m-d'],
        ], ['UuidAkun.*' => 'Pilih rekening bank.', 'Tanggal.*' => 'Isi tanggal cair.']);
        $g = $cairkan->Jalankan(
            $this->CariGiro($giro),
            strtoupper((string) $data['UuidAkun']),
            CarbonImmutable::createFromFormat('!Y-m-d', (string) $data['Tanggal']) ?: CarbonImmutable::today(),
            $this->Pelaku()->Id,
        );

        return back()->with('Kilat', "Giro {$g->NomorGiro} dicatat cair dan dijurnal.");
    }

    public function Tolak(string $giro, Request $permintaan, TolakGiro $tolak): RedirectResponse
    {
        $data = $permintaan->validate(['Alasan' => ['required', 'string', 'min:5', 'max:255']]);
        $g = $tolak->Jalankan($this->CariGiro($giro), (string) $data['Alasan'], $this->Pelaku()->Id);

        return back()->with('Kilat', "Giro {$g->NomorGiro} ditolak; {$g->NomorSumber} dibatalkan dan ".($g->Arah === ArahGiro::Masuk ? 'piutangnya' : 'hutangnya').' kembali terbuka.');
    }

    private function CariGiro(string $uuid): Giro
    {
        return Giro::query()->where('Uuid', strtoupper($uuid))->firstOrFail();
    }
}
