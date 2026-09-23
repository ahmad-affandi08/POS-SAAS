<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Referensi;

use App\Domain\Bersama\Status\StatusDataMaster;
use App\Domain\Pengelola\Referensi\Aksi\AjukanHariLiburTahun;
use App\Domain\Pengelola\Referensi\Aksi\SimpanDrafHariLibur;
use App\Domain\Pengelola\Referensi\Aksi\TinjauHariLiburTahun;
use App\Domain\Pengelola\Referensi\Model\PersetujuanDataMaster;
use App\Domain\Referensi\Enum\JenisHariLibur;
use App\Domain\Referensi\Model\HariLibur;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use App\Http\Permintaan\Pengelola\Referensi\SimpanHariLiburPermintaan;
use App\Http\Permintaan\Pengelola\Referensi\TinjauDataMasterPermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hari libur nasional & cuti bersama per tahun (P-02, BR-P02.2, BR-P02.4).
 */
final class HariLiburKontroler extends Kontroler
{
    use PelakuPengelola;

    public function Daftar(Request $permintaan): Response
    {
        $tahun = $permintaan->integer('tahun', (int) now('Asia/Jakarta')->addYear()->year);
        $tahun = max(2000, min(2100, $tahun));
        $hari = HariLibur::query()->whereYear('Tanggal', $tahun)->orderBy('Tanggal')->get();
        $menunggu = $hari->firstWhere('Status', StatusDataMaster::MenungguTinjauan);

        return Inertia::render('Pengelola/Referensi/HariLibur', [
            'Tahun' => $tahun,
            'HariLibur' => $hari->map(fn (HariLibur $item): array => [
                'Uuid' => $item->Uuid,
                'Tanggal' => $item->Tanggal->toDateString(),
                'Nama' => $item->Nama,
                'Jenis' => $item->Jenis->value,
                'Status' => $item->Status->value,
                'NomorDasarHukum' => $item->NomorDasarHukum,
            ])->values()->all(),
            'IdPengajuMenunggu' => $menunggu?->IdPenggunaPengelolaPengaju,
            'PeninjauMenunggu' => $menunggu?->DiajukanPada === null ? [] : PersetujuanDataMaster::query()
                ->where('JenisData', TinjauHariLiburTahun::JENIS_DATA)
                ->where('IdData', $tahun)
                ->where('DibuatPada', '>=', $menunggu->DiajukanPada)
                ->pluck('IdPenggunaPengelola')
                ->all(),
            'IdPengguna' => $this->AmbilPelaku()->Id,
            'PilihanJenis' => array_map(fn (JenisHariLibur $jenis) => ['Nilai' => $jenis->value, 'Label' => $jenis->AmbilLabel()], JenisHariLibur::cases()),
        ]);
    }

    public function Simpan(SimpanHariLiburPermintaan $permintaan, SimpanDrafHariLibur $simpan): RedirectResponse
    {
        $hari = $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilData());

        return back()->with('Kilat', "Draf {$hari->Nama} disimpan.");
    }

    public function Ubah(HariLibur $hariLibur, SimpanHariLiburPermintaan $permintaan, SimpanDrafHariLibur $simpan): RedirectResponse
    {
        $simpan->Jalankan($this->AmbilPelaku(), $permintaan->AmbilData(), $hariLibur);

        return back()->with('Kilat', 'Draf hari libur diperbarui.');
    }

    public function Hapus(HariLibur $hariLibur, SimpanDrafHariLibur $simpan): RedirectResponse
    {
        $simpan->Hapus($this->AmbilPelaku(), $hariLibur);

        return back()->with('Kilat', 'Draf hari libur dihapus.');
    }

    public function Ajukan(int $tahun, AjukanHariLiburTahun $ajukan): RedirectResponse
    {
        $jumlah = $ajukan->Jalankan($this->AmbilPelaku(), $tahun);

        return back()->with('Kilat', "{$jumlah} hari libur tahun {$tahun} diajukan untuk ditinjau.");
    }

    public function Tinjau(int $tahun, TinjauDataMasterPermintaan $permintaan, TinjauHariLiburTahun $tinjau): RedirectResponse
    {
        $status = $tinjau->Jalankan($this->AmbilPelaku(), $tahun, $permintaan->AmbilKeputusan(), $permintaan->AmbilCatatan());

        return back()->with('Kilat', $status === StatusDataMaster::Terbit
            ? "Hari libur tahun {$tahun} terbit."
            : "Hari libur tahun {$tahun} dikembalikan ke draf.");
    }
}
