<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Dukungan\Aksi\BuatTiketDukungan;
use App\Domain\Dukungan\Data\DataTiketBaru;
use App\Domain\Dukungan\Enum\KategoriTiketDukungan;
use App\Domain\Dukungan\Enum\PrioritasTiketDukungan;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Tenant\Aksi\BatalkanTagihanLangganan;
use App\Domain\Tenant\Aksi\BuatTagihanLangganan;
use App\Domain\Tenant\Aksi\UnggahBuktiTransfer;
use App\Domain\Tenant\Enum\StatusPembayaranLangganan;
use App\Domain\Tenant\Kueri\PenawaranFiturTenant;
use App\Domain\Tenant\Kueri\RekeningTujuanPlatform;
use App\Domain\Tenant\Kueri\TagihanLanggananTenant;
use App\Domain\Tenant\Model\PembayaranLangganan;
use App\Http\Kontroler\Kontroler;
use App\Http\Permintaan\Kelola\Langganan\BatalkanTagihanLanggananPermintaan;
use App\Http\Permintaan\Kelola\Langganan\BuatTagihanLanggananPermintaan;
use App\Http\Permintaan\Kelola\Langganan\UnggahBuktiTransferPermintaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $unggah->Jalankan($tagihan, $permintaan->AmbilData(), $pengguna->Id, $pengguna->Nama, (string) $pengguna->Email);

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

    /**
     * D-23: minta add-on untuk fitur terkunci dari dialog menu. Pembelian add-on mandiri belum tersedia (F-19), jadi
     * permintaan menjadi tiket dukungan kategori Akun & langganan; tim platform mengaktifkannya lalu menagih.
     */
    public function MintaAddon(Request $permintaan, PenawaranFiturTenant $penawaran, BuatTiketDukungan $buatTiket): RedirectResponse
    {
        $kunci = (string) $permintaan->validate(['KunciFitur' => ['required', 'string', 'max:60']], attributes: ['KunciFitur' => 'fitur'])['KunciFitur'];
        $pengguna = $this->PenggunaMasuk();
        $fitur = $penawaran->Ambil(app(KonteksTenant::class)->Wajib())['Terkunci'][$kunci] ?? null;
        $addon = $fitur['Addon'] ?? null;

        if ($fitur === null || $addon === null) {
            return back()->withErrors(['Umum' => 'Add-on untuk fitur ini tidak tersedia atau fitur sudah aktif.']);
        }

        $harga = Uang::Dari($addon['HargaBulanan'])->FormatRupiah();
        $tiket = $buatTiket->Jalankan($pengguna->Id, $pengguna->Nama, new DataTiketBaru(
            KategoriTiketDukungan::AkunLangganan,
            PrioritasTiketDukungan::Normal,
            "Permintaan add-on {$addon['Nama']}",
            "Mohon aktifkan add-on {$addon['Nama']} ({$harga}/bulan) untuk membuka fitur \"{$fitur['Nama']}\". Tagihan dikirim ke usaha ini.",
            konteks: ['KodeAddon' => $addon['Kode'], 'KunciFitur' => $kunci],
        ));

        return redirect()->route('kelola.bantuan.tampil', ['tiketDukungan' => $tiket->Uuid])
            ->with('Kilat', "Permintaan add-on {$addon['Nama']} terkirim. Tim kami akan mengaktifkannya dan mengirim tagihan.");
    }

    private function PenggunaMasuk(): Pengguna
    {
        $pengguna = Auth::guard('web')->user();
        abort_unless($pengguna instanceof Pengguna, 403);

        return $pengguna;
    }
}
