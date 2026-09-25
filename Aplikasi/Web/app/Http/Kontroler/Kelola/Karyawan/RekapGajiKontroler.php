<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Karyawan;

use App\Domain\Akuntansi\Enum\TipeAkun;
use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Karyawan\Aksi\KelolaRekapGaji;
use App\Domain\Karyawan\Enum\StatusRekapGaji;
use App\Domain\Karyawan\Kueri\DaftarRekapGaji;
use App\Domain\Karyawan\Model\RekapGaji;
use App\Http\Kontroler\Kelola\DasarKelolaKontroler;
use App\Http\Respons\ResponsTabel;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Rekap gaji bulanan (F-18 bagian 3, `/kelola/karyawan/gaji`). Seluruhnya butuh `karyawan.kelola` karena memuat gaji.
 */
final class RekapGajiKontroler extends DasarKelolaKontroler
{
    private const ATURAN_UANG = ['required', 'string', 'regex:/^\d{1,15}(\.\d{1,2})?$/'];

    public function Daftar(Request $permintaan, DaftarRekapGaji $daftar): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarRekapGaji::KOLOM_URUT, DaftarRekapGaji::URUT_BAWAAN, DaftarRekapGaji::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Karyawan/Gaji', 'Rekap', fn (): array => $daftar->Ambil($tabel), fn (): array => [
            'OpsiPeriode' => $daftar->AmbilOpsiPeriode(),
            'OpsiStatus' => array_map(fn (StatusRekapGaji $s): array => ['Nilai' => $s->value, 'Label' => $s->AmbilLabel()], StatusRekapGaji::cases()),
        ]);
    }

    public function Simpan(Request $permintaan, KelolaRekapGaji $kelola): RedirectResponse
    {
        $data = $permintaan->validate(['Periode' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/']], ['Periode.*' => 'Pilih periode bulan.']);
        $rekap = $kelola->Buat($data['Periode'], $this->Pelaku()->Id);

        return to_route('kelola.karyawan.gaji.detail', ['rekap' => $rekap->Uuid])->with('Kilat', "Draf rekap gaji {$rekap->Periode} dibuat.");
    }

    public function Detail(string $rekap, DaftarRekapGaji $daftar, DaftarAkunPilihan $akun): Response
    {
        $model = RekapGaji::query()->where('Uuid', $rekap)->firstOrFail();
        $pilihan = fn (array $a): array => ['Uuid' => $a['Uuid'], 'Kode' => $a['Kode'], 'Nama' => $a['Kode'].' '.$a['Nama']];

        return Inertia::render('Kelola/Karyawan/DetailGaji', $daftar->AmbilDetail($model) + [
            'OpsiAkunKasBank' => array_map($pilihan, $akun->AmbilKasBank()),
            'OpsiAkunBeban' => array_map($pilihan, $akun->Ambil([TipeAkun::Beban])),
        ]);
    }

    public function UbahBaris(string $rekap, string $karyawan, Request $permintaan, KelolaRekapGaji $kelola): RedirectResponse
    {
        $data = $permintaan->validate([
            'Tambahan' => self::ATURAN_UANG,
            'PotonganKasbon' => self::ATURAN_UANG,
            'PotonganLain' => self::ATURAN_UANG,
            'Catatan' => ['nullable', 'string', 'max:255'],
        ], [
            'Tambahan.*' => 'Isi tambahan dalam rupiah, misal 0 atau 150000.',
            'PotonganKasbon.*' => 'Isi potongan kasbon dalam rupiah.',
            'PotonganLain.*' => 'Isi potongan lain dalam rupiah.',
            'Catatan.*' => 'Catatan paling panjang 255 karakter.',
        ]);
        $catatan = is_string($data['Catatan'] ?? null) && trim($data['Catatan']) !== '' ? trim($data['Catatan']) : null;
        $model = RekapGaji::query()->where('Uuid', $rekap)->firstOrFail();
        $kelola->UbahBaris($model, $karyawan, Uang::Dari($data['Tambahan']), Uang::Dari($data['PotonganKasbon']), Uang::Dari($data['PotonganLain']), $catatan);

        return to_route('kelola.karyawan.gaji.detail', ['rekap' => $model->Uuid])->with('Kilat', 'Baris gaji diperbarui.');
    }

    public function Bayar(string $rekap, Request $permintaan, KelolaRekapGaji $kelola): RedirectResponse
    {
        $data = $permintaan->validate([
            'Tanggal' => ['required', 'date_format:Y-m-d'],
            'AkunKasBank' => ['required', 'string', 'size:26'],
            'AkunBeban' => ['required', 'string', 'size:26'],
        ], [
            'Tanggal.*' => 'Isi tanggal bayar.',
            'AkunKasBank.*' => 'Pilih akun kas atau bank.',
            'AkunBeban.*' => 'Pilih akun beban gaji.',
        ]);
        $model = RekapGaji::query()->where('Uuid', $rekap)->firstOrFail();
        $tanggal = CarbonImmutable::createFromFormat('!Y-m-d', $data['Tanggal']) ?: CarbonImmutable::today();
        $kelola->Bayar($model, $tanggal, $data['AkunKasBank'], $data['AkunBeban'], $this->Pelaku()->Id);

        return to_route('kelola.karyawan.gaji.detail', ['rekap' => $model->Uuid])->with('Kilat', "Gaji {$model->Periode} dibayar dan dijurnal.");
    }

    public function Hapus(string $rekap, KelolaRekapGaji $kelola): RedirectResponse
    {
        $model = RekapGaji::query()->where('Uuid', $rekap)->firstOrFail();
        $kelola->Hapus($model);

        return to_route('kelola.karyawan.gaji')->with('Kilat', "Draf rekap gaji {$model->Periode} dihapus.");
    }

    public function Ekspor(string $rekap, DaftarRekapGaji $daftar): StreamedResponse
    {
        $model = RekapGaji::query()->where('Uuid', $rekap)->firstOrFail();
        $detail = $daftar->AmbilDetail($model);
        $kolom = ['Nama', 'Jabatan', 'GajiPokok', 'Komisi', 'Tambahan', 'Kotor', 'PotonganKasbon', 'PotonganLain', 'Bersih', 'Catatan'];

        return response()->streamDownload(function () use ($detail, $kolom): void {
            $keluaran = fopen('php://output', 'w');

            if ($keluaran === false) {
                return;
            }

            fputcsv($keluaran, $kolom, escape: '');

            foreach ($detail['Baris'] as $b) {
                fputcsv($keluaran, array_map(fn (string $k): string => self::AmankanSel((string) ($b[$k] ?? '')), $kolom), escape: '');
            }

            fclose($keluaran);
        }, "RekapGaji-{$model->Periode}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Cegah injeksi rumus spreadsheet pada teks bebas (nama, catatan). */
    private static function AmankanSel(string $nilai): string
    {
        return $nilai !== '' && in_array($nilai[0], ['=', '+', '-', '@'], true) && ! is_numeric($nilai) ? "'".$nilai : $nilai;
    }
}
