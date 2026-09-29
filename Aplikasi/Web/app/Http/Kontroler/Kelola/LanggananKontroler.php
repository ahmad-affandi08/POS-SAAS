<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Dukungan\Aksi\BuatTiketDukungan;
use App\Domain\Dukungan\Data\DataTiketBaru;
use App\Domain\Dukungan\Enum\KategoriTiketDukungan;
use App\Domain\Dukungan\Enum\PrioritasTiketDukungan;
use App\Domain\Integrasi\Billing\GerbangBillingPlatform;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Tenant\Aksi\BatalkanTagihanLangganan;
use App\Domain\Tenant\Aksi\BuatTagihanLangganan;
use App\Domain\Tenant\Aksi\MulaiPembayaranGerbangLangganan;
use App\Domain\Tenant\Enum\StatusPembayaranLangganan;
use App\Domain\Tenant\Kueri\PenawaranFiturTenant;
use App\Domain\Tenant\Kueri\TagihanLanggananTenant;
use App\Domain\Tenant\Model\PembayaranLangganan;
use App\Http\Kontroler\Kontroler;
use App\Http\Permintaan\Kelola\Langganan\BatalkanTagihanLanggananPermintaan;
use App\Http\Permintaan\Kelola\Langganan\BuatTagihanLanggananPermintaan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Langganan & tagihan di back-office tenant: lihat paket & status, buat tagihan,
 * bayar online via gerbang billing. Rute dijaga `WajibIzinTenant` dengan izin `langganan.kelola`.
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
            ->with('Kilat', "Tagihan {$tagihan->Nomor} dibuat. Silakan selesaikan pembayaran online.");
    }

    public function TampilkanTagihan(string $tagihan, GerbangBillingPlatform $gerbang): Response
    {
        $data = $this->kueri->CariTagihan($tagihan);
        abort_if($data === null, 404);
        $pembayaran = $data->Pembayaran->sortByDesc('Id')->values();
        // Pembatalan tetap diblokir pembayaran `Menunggu` apa pun, termasuk transaksi gerbang yang sedang berjalan:
        // kalau tidak, tagihan bisa dibatalkan tepat saat uangnya sedang masuk.
        $adaPembayaranMenunggu = $pembayaran->contains(fn (PembayaranLangganan $baris): bool => $baris->Status === StatusPembayaranLangganan::Menunggu);
        $terbuka = $data->Status->CekTerbuka();

        return Inertia::render('Kelola/Langganan/Tagihan', [
            'Tagihan' => TagihanLanggananTenant::PetakanTagihan($data),
            'Pembayaran' => array_values($pembayaran->map(fn (PembayaranLangganan $baris): array => TagihanLanggananTenant::PetakanPembayaran($baris))->all()),
            'BolehBayarOnline' => $terbuka && $gerbang->CekAktif(),
            'BolehBatalkan' => $terbuka && ! $adaPembayaranMenunggu,
            'Gerbang' => $gerbang->CekAktif() ? ['KunciKlien' => $gerbang->KunciKlien(), 'UrlSnapJs' => $gerbang->UrlSnapJs()] : null,
        ]);
    }

    /**
     * Membuat transaksi Snap untuk tagihan ini (BR-P08.11). Menjawab JSON, bukan redirect Inertia, karena token-nya
     * dipakai langsung oleh popup Snap.js di halaman. Tagihan tidak pernah lunas dari sini: pelunasan hanya dari
     * notifikasi webhook bertanda tangan.
     */
    public function BayarOnline(string $tagihan, MulaiPembayaranGerbangLangganan $mulai): JsonResponse
    {
        $pengguna = $this->PenggunaMasuk();
        $hasil = $mulai->Jalankan($tagihan, $pengguna->Id, $pengguna->Nama, (string) $pengguna->Email);

        return response()->json(['Token' => $hasil->token, 'UrlRedirect' => $hasil->urlRedirect]);
    }

    public function Batalkan(string $tagihan, BatalkanTagihanLanggananPermintaan $permintaan, BatalkanTagihanLangganan $batalkan): RedirectResponse
    {
        $hasil = $batalkan->Jalankan($tagihan, $permintaan->AmbilAlasan());

        return redirect()->route('kelola.langganan.tampil')->with('Kilat', "Tagihan {$hasil->Nomor} dibatalkan.");
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
