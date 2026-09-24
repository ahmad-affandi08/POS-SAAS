<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Dukungan\Aksi\BalasTiketDukungan;
use App\Domain\Dukungan\Aksi\BuatTiketDukungan;
use App\Domain\Dukungan\Aksi\SelesaikanTiketDukungan;
use App\Domain\Dukungan\Enum\KategoriTiketDukungan;
use App\Domain\Dukungan\Enum\PrioritasTiketDukungan;
use App\Domain\Dukungan\Kueri\TiketDukunganTenant;
use App\Domain\Dukungan\Layanan\PenyimpanLampiran;
use App\Domain\Organisasi\Model\Pengguna;
use App\Http\Kontroler\Kontroler;
use App\Http\Permintaan\Kelola\BalasTiketDukunganPermintaan;
use App\Http\Permintaan\Kelola\BuatTiketDukunganPermintaan;
use App\Http\Respons\ResponsTabel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bantuan di back-office tenant (P-09): buat tiket, lihat daftar & percakapan, balas, tandai selesai.
 * Semua data lewat MilikTenant (tenant aktif dari sesi). TODO F-02: batasi dengan izin peran tenant setelah RBAC
 * tenant tersedia; sementara semua anggota aktif tenant boleh memakai Bantuan dan melihat semua tiket tenant.
 */
final class BantuanKontroler extends Kontroler
{
    public function Daftar(Request $permintaan, TiketDukunganTenant $kueri): Response|JsonResponse
    {
        $tabel = DataPermintaanTabel::Dari($permintaan->query(), TiketDukunganTenant::KOLOM_URUT, '-DibuatPada', TiketDukunganTenant::KOLOM_SARING);

        return ResponsTabel::Kirim($permintaan, 'Kelola/Bantuan/Daftar', 'Tiket', fn (): array => $kueri->AmbilTabel($tabel));
    }

    public function Buat(): Response
    {
        return Inertia::render('Kelola/Bantuan/Buat', [
            'Kategori' => array_map(fn (KategoriTiketDukungan $kategori) => ['Nilai' => $kategori->value, 'Label' => $kategori->AmbilLabel()], KategoriTiketDukungan::cases()),
            'Prioritas' => array_map(fn (PrioritasTiketDukungan $prioritas) => [
                'Nilai' => $prioritas->value,
                'Label' => $prioritas->AmbilLabel(),
                'Keterangan' => $prioritas->AmbilKeterangan(),
            ], PrioritasTiketDukungan::cases()),
            'Lampiran' => self::AmbilBatasLampiran(),
        ]);
    }

    public function Simpan(BuatTiketDukunganPermintaan $permintaan, BuatTiketDukungan $buat): RedirectResponse
    {
        $pengguna = $this->AmbilPengguna();
        $tiket = $buat->Jalankan($pengguna->Id, $pengguna->Nama, $permintaan->AmbilData());

        return redirect()
            ->route('kelola.bantuan.tampil', $tiket->Uuid)
            ->with('Kilat', "Tiket {$tiket->Nomor} terkirim. Tim Dukungan akan membalas paling lambat {$tiket->BatasSlaPada->setTimezone('Asia/Jakarta')->translatedFormat('j F Y H:i')} WIB.");
    }

    public function Tampilkan(string $tiketDukungan, TiketDukunganTenant $kueri): Response
    {
        return Inertia::render('Kelola/Bantuan/Tiket', [
            'Tiket' => $kueri->AmbilDetail($kueri->Cari($tiketDukungan)),
            'Lampiran' => self::AmbilBatasLampiran(),
        ]);
    }

    public function Balas(string $tiketDukungan, BalasTiketDukunganPermintaan $permintaan, TiketDukunganTenant $kueri, BalasTiketDukungan $balas): RedirectResponse
    {
        $pengguna = $this->AmbilPengguna();
        $balas->Jalankan($pengguna->Id, $pengguna->Nama, $kueri->Cari($tiketDukungan), $permintaan->AmbilIsi(), $permintaan->AmbilLampiran());

        return back()->with('Kilat', 'Balasan terkirim.');
    }

    public function Selesaikan(string $tiketDukungan, TiketDukunganTenant $kueri, SelesaikanTiketDukungan $selesaikan): RedirectResponse
    {
        $hari = (int) config('dukungan.HariBukaUlang');
        $selesaikan->Jalankan($this->AmbilPengguna()->Nama, $kueri->Cari($tiketDukungan));

        return back()->with('Kilat', "Tiket ditandai selesai. Balas dalam {$hari} hari bila masalahnya muncul lagi.");
    }

    public function UnduhLampiran(string $tiketDukungan, string $lampiran, TiketDukunganTenant $kueri, PenyimpanLampiran $penyimpan): StreamedResponse
    {
        $data = $kueri->CariLampiran($kueri->Cari($tiketDukungan), $lampiran);
        abort_if($data === null, 404);

        return $penyimpan->Unduh($data);
    }

    /**
     * @return array{Maksimal: int, UkuranMaksimalKb: int, Ekstensi: list<string>}
     */
    public static function AmbilBatasLampiran(): array
    {
        return [
            'Maksimal' => (int) config('dukungan.MaksimalLampiranPerPesan'),
            'UkuranMaksimalKb' => (int) config('dukungan.UkuranMaksimalLampiranKb'),
            'Ekstensi' => array_values(array_map('strval', (array) config('dukungan.EkstensiLampiran'))),
        ];
    }

    private function AmbilPengguna(): Pengguna
    {
        $pengguna = Auth::guard('web')->user();
        abort_unless($pengguna instanceof Pengguna, 403);

        return $pengguna;
    }
}
