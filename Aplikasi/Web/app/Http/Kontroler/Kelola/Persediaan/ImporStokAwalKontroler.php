<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola\Persediaan;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Katalog\Impor\Layanan\PembacaBerkasTabel;
use App\Domain\Persediaan\Enum\StatusImporStokAwal;
use App\Domain\Persediaan\Impor\Aksi\BatalkanImporStokAwal;
use App\Domain\Persediaan\Impor\Aksi\LanjutkanImporStokAwal;
use App\Domain\Persediaan\Impor\Aksi\SimpanPemetaanImporStokAwal;
use App\Domain\Persediaan\Impor\Aksi\TerapkanImporStokAwal;
use App\Domain\Persediaan\Impor\Aksi\UnggahImporStokAwal;
use App\Domain\Persediaan\Impor\Kueri\DaftarImporStokAwal;
use App\Domain\Persediaan\Impor\Kueri\DetailImporStokAwal;
use App\Domain\Persediaan\Impor\Layanan\PenulisBerkasImporStokAwal;
use App\Domain\Persediaan\Model\ImporStokAwal;
use App\Http\Permintaan\Kelola\Persediaan\SimpanPemetaanImporStokAwalPermintaan;
use App\Http\Permintaan\Kelola\Persediaan\UnggahImporStokAwalPermintaan;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Impor stok awal Excel/CSV (DesainF05a C.7 & D, routes/PersediaanImpor.php, izin `persediaan.kelola`): riwayat &
 * unggah, templat, detail (pemetaan → pratinjau → pembuatan draf), status polling, terapkan/lanjutkan/batalkan, dan
 * laporan xlsx/csv. `{imporStokAwal}` dicari lewat ULID di scope tenant aktif (tenant lain = 404). Pelaku yang
 * aksesnya dibatasi per outlet hanya melihat impornya sendiri (lainnya = 404); lokasi stok di luar akses = 404.
 * Impor hanya membuat draf; posting tetap per dokumen di halaman stok awal.
 */
final class ImporStokAwalKontroler extends DasarPersediaanKontroler
{
    public function Daftar(Request $permintaan): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), DaftarImporStokAwal::KOLOM_URUT, DaftarImporStokAwal::URUT_BAWAAN, DaftarImporStokAwal::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Persediaan/StokAwal/Impor/Daftar', 'Riwayat', fn (): array => app(DaftarImporStokAwal::class)->AmbilTabel($tabel, $this->HanyaPengguna()), fn (): array => [
            'OpsiStatus' => array_map(fn (StatusImporStokAwal $s): array => ['Nilai' => $s->value, 'Label' => $s->AmbilLabel()], StatusImporStokAwal::cases()),
            'OpsiGudang' => $this->AmbilOpsiGudang(),
            'BatasBerkas' => [
                'UkuranMaksimalKb' => (int) config('persediaan.Impor.UkuranMaksimalKb', 10240),
                'MaksimalBaris' => (int) config('persediaan.Impor.MaksimalBaris', 20000),
                'Ekstensi' => [PembacaBerkasTabel::FORMAT_XLSX, PembacaBerkasTabel::FORMAT_CSV],
            ],
        ]);
    }

    public function Templat(Request $permintaan): StreamedResponse
    {
        $uuidGudang = $permintaan->query('gudang');
        $gudang = is_string($uuidGudang) && $uuidGudang !== '' ? $this->CariGudangBoleh($uuidGudang) : null;

        return app(PenulisBerkasImporStokAwal::class)->AlirkanTemplat(
            $permintaan->query('format') === 'csv' ? 'csv' : 'xlsx',
            $permintaan->query('isi') === 'produk',
            $gudang,
        );
    }

    public function Unggah(UnggahImporStokAwalPermintaan $permintaan): RedirectResponse
    {
        $uuidGudang = $permintaan->AmbilUuidGudangBawaan();
        $idGudang = $uuidGudang === null ? null : $this->CariGudangBoleh($uuidGudang)->id;
        $impor = app(UnggahImporStokAwal::class)->Jalankan($permintaan->AmbilBerkas(), $idGudang, $this->Pelaku()->Id);

        return redirect()->route('kelola.persediaan.stok-awal.impor.detail', ['imporStokAwal' => $impor->Uuid])
            ->with('Kilat', "Berkas {$impor->NamaBerkas} diunggah. Periksa pemetaan kolom.");
    }

    public function Detail(string $imporStokAwal): Response
    {
        $impor = $this->CariImpor($imporStokAwal);

        return Inertia::render('Kelola/Persediaan/StokAwal/Impor/Detail', [
            ...app(DetailImporStokAwal::class)->Ambil($impor),
            'OpsiGudang' => $this->AmbilOpsiGudang(),
        ]);
    }

    public function Status(string $imporStokAwal): JsonResponse
    {
        return response()->json(app(DetailImporStokAwal::class)->AmbilStatus($this->CariImpor($imporStokAwal)));
    }

    public function SimpanPemetaan(SimpanPemetaanImporStokAwalPermintaan $permintaan, string $imporStokAwal): RedirectResponse
    {
        $impor = $this->CariImpor($imporStokAwal);
        $uuidGudang = $permintaan->AmbilUuidGudangBawaan();
        $idGudang = $uuidGudang === null ? null : $this->CariGudangBoleh($uuidGudang)->id;
        $impor = app(SimpanPemetaanImporStokAwal::class)->Jalankan($impor, $permintaan->AmbilPemetaan(), $idGudang, $permintaan->AmbilTanggal());

        return redirect()->route('kelola.persediaan.stok-awal.impor.detail', ['imporStokAwal' => $impor->Uuid]);
    }

    public function Terapkan(string $imporStokAwal): RedirectResponse
    {
        $impor = app(TerapkanImporStokAwal::class)->Jalankan($this->CariImpor($imporStokAwal));

        return redirect()->route('kelola.persediaan.stok-awal.impor.detail', ['imporStokAwal' => $impor->Uuid]);
    }

    public function Lanjutkan(string $imporStokAwal): RedirectResponse
    {
        $impor = app(LanjutkanImporStokAwal::class)->Jalankan($this->CariImpor($imporStokAwal));

        return redirect()->route('kelola.persediaan.stok-awal.impor.detail', ['imporStokAwal' => $impor->Uuid]);
    }

    public function Batalkan(string $imporStokAwal): RedirectResponse
    {
        $impor = app(BatalkanImporStokAwal::class)->Jalankan($this->CariImpor($imporStokAwal));

        return redirect()->route('kelola.persediaan.stok-awal.impor.detail', ['imporStokAwal' => $impor->Uuid])->with('Kilat', 'Impor dibatalkan.');
    }

    public function Laporan(Request $permintaan, string $imporStokAwal): StreamedResponse
    {
        return app(PenulisBerkasImporStokAwal::class)->AlirkanLaporan(
            $this->CariImpor($imporStokAwal),
            $permintaan->query('jenis') === 'semua' ? 'semua' : 'galat',
            $permintaan->query('format') === 'csv' ? 'csv' : 'xlsx',
        );
    }

    private function CariImpor(string $uuid): ImporStokAwal
    {
        return ImporStokAwal::query()
            ->where('Uuid', $uuid)
            ->when($this->HanyaPengguna() !== null, fn ($kueri) => $kueri->where('IdPengguna', $this->HanyaPengguna()))
            ->firstOrFail();
    }

    /** Pelaku berakses per outlet hanya melihat impornya sendiri; null = semua impor tenant. */
    private function HanyaPengguna(): ?int
    {
        return $this->IdOutletBoleh() === null ? null : $this->Pelaku()->Id;
    }
}
