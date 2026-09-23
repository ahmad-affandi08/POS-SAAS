<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Tenant\Aksi\BatalkanTagihanLangganan;
use App\Domain\Tenant\Aksi\BuatTagihanLangganan;
use App\Domain\Tenant\Aksi\UnggahBuktiTransfer;
use App\Domain\Tenant\Enum\StatusPembayaranLangganan;
use App\Domain\Tenant\Kueri\RekeningTujuanPlatform;
use App\Domain\Tenant\Kueri\TagihanLanggananTenant;
use App\Domain\Tenant\Model\PembayaranLangganan;
use App\Http\Kontroler\Kontroler;
use App\Http\Permintaan\Kelola\Langganan\BatalkanTagihanLanggananPermintaan;
use App\Http\Permintaan\Kelola\Langganan\BuatTagihanLanggananPermintaan;
use App\Http\Permintaan\Kelola\Langganan\UnggahBuktiTransferPermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Langganan & tagihan di back-office tenant (P-08/F-19 Fase 0): lihat paket & status, buat tagihan, transfer
 * manual dengan unggah bukti, lihat status verifikasi. Rute dijaga `WajibIzinTenant` dengan izin `langganan.kelola`
 * (khusus Pemilik, §19.1).
 */
final class LanggananKontroler extends Kontroler
{
    public function __construct(private readonly TagihanLanggananTenant $kueri) {}

    public function Tampilkan(): Response
    {

        return Inertia::render('Kelola/Langganan/Indeks', [
            'Langganan' => $this->kueri->AmbilLangganan(),
            'PilihanPaket' => $this->kueri->AmbilPilihanPaket(),
            'Tagihan' => $this->kueri->DaftarTagihan(),
            'HariMasaTenggang' => (int) config('tagihan.HariMasaTenggang'),
        ]);
    }

    public function BuatTagihan(BuatTagihanLanggananPermintaan $permintaan, BuatTagihanLangganan $buat): RedirectResponse
    {
        $pengguna = $this->PenggunaMasuk();
        $tagihan = $buat->Jalankan(
            $pengguna->Id,
            $permintaan->string('KodePaket')->toString(),
            $permintaan->AmbilSiklus(),
            $permintaan->AmbilKodeKupon(),
        );

        return redirect()->route('kelola.langganan.tagihan.tampil', ['tagihan' => $tagihan->Uuid])
            ->with('Kilat', "Tagihan {$tagihan->Nomor} dibuat. Transfer sesuai total, lalu unggah buktinya di halaman ini.");
    }

    public function TampilkanTagihan(string $tagihan, RekeningTujuanPlatform $rekening): Response
    {
        $data = $this->kueri->CariTagihan($tagihan);
        abort_if($data === null, 404);
        $pembayaran = $data->Pembayaran->sortByDesc('Id')->values();

        return Inertia::render('Kelola/Langganan/Tagihan', [
            'Tagihan' => TagihanLanggananTenant::PetakanTagihan($data),
            'Pembayaran' => array_values($pembayaran->map(fn (PembayaranLangganan $baris): array => TagihanLanggananTenant::PetakanPembayaran($baris))->all()),
            'RekeningTujuan' => $rekening->Ambil(),
            'BolehUnggah' => $data->Status->CekTerbuka()
                && ! $pembayaran->contains(fn (PembayaranLangganan $baris): bool => $baris->Status === StatusPembayaranLangganan::Menunggu),
            'UkuranBuktiMaksimalKb' => (int) config('tagihan.UkuranBuktiMaksimalKb'),
        ]);
    }

    public function UnggahBukti(string $tagihan, UnggahBuktiTransferPermintaan $permintaan, UnggahBuktiTransfer $unggah): RedirectResponse
    {
        $pengguna = $this->PenggunaMasuk();
        $unggah->Jalankan($tagihan, $permintaan->AmbilData(), $pengguna->Id, $pengguna->Nama, $pengguna->Email);

        return back()->with('Kilat', 'Bukti transfer terkirim. Kami memverifikasinya pada hari kerja dan mengabari Anda lewat email.');
    }

    public function Batalkan(string $tagihan, BatalkanTagihanLanggananPermintaan $permintaan, BatalkanTagihanLangganan $batalkan): RedirectResponse
    {
        $hasil = $batalkan->Jalankan($tagihan, $permintaan->AmbilAlasan());

        return redirect()->route('kelola.langganan.tampil')->with('Kilat', "Tagihan {$hasil->Nomor} dibatalkan.");
    }

    /** Bukti disajikan dari disk privat hanya ke pemegang `langganan.kelola` tenant pemiliknya (lingkup MilikTenant). */
    public function LihatBukti(string $pembayaran): StreamedResponse
    {
        $data = $this->kueri->CariPembayaran($pembayaran);
        $disk = Storage::disk((string) config('tagihan.DiskBukti'));
        abort_if($data === null || $data->PathBukti === null || ! $disk->exists($data->PathBukti), 404);

        return $disk->response($data->PathBukti, 'bukti-transfer.'.pathinfo($data->PathBukti, PATHINFO_EXTENSION), [
            'Content-Type' => $data->MimeBukti ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function PenggunaMasuk(): Pengguna
    {
        $pengguna = Auth::guard('web')->user();
        abort_unless($pengguna instanceof Pengguna, 403);

        return $pengguna;
    }
}
