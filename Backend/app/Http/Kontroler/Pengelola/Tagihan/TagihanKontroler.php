<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pengelola\Tagihan;

use App\Domain\Pengelola\Tagihan\Aksi\BukaBuktiPembayaran;
use App\Domain\Pengelola\Tagihan\Aksi\TerimaPembayaranLangganan;
use App\Domain\Pengelola\Tagihan\Aksi\TolakPembayaranLangganan;
use App\Domain\Pengelola\Tagihan\Kueri\DaftarTagihanPlatform;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Tenant\Enum\StatusTagihanLangganan;
use App\Domain\Tenant\Kueri\TagihanLanggananTenant;
use App\Domain\Tenant\Model\PembayaranLangganan;
use App\Domain\Tenant\Model\TagihanLangganan;
use App\Http\Kontroler\Kontroler;
use App\Http\Kontroler\Pengelola\PelakuPengelola;
use App\Http\Permintaan\Pengelola\Tagihan\TerimaPembayaranPermintaan;
use App\Http\Permintaan\Pengelola\Tagihan\TolakPembayaranPermintaan;
use App\Http\Respons\DaftarBerhalaman;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tagihan langganan & antrean verifikasi transfer manual (P-08, §19.3: Keuangan & Super Admin).
 */
final class TagihanKontroler extends Kontroler
{
    use PelakuPengelola;

    public function __construct(private readonly DaftarTagihanPlatform $kueri) {}

    public function Daftar(Request $permintaan): Response
    {
        $kata = trim($permintaan->string('kata')->toString());
        $status = StatusTagihanLangganan::tryFrom($permintaan->string('status')->toString());
        $halaman = $this->kueri->AmbilTagihan($status, $kata);
        /** @var list<TagihanLangganan> $isi */
        $isi = $halaman->items();
        $namaTenant = $this->kueri->AmbilNamaTenant(array_map(fn (TagihanLangganan $tagihan): int => $tagihan->IdTenant, $isi));

        return Inertia::render('Pengelola/Tagihan/Daftar', [
            'Antrean' => $this->kueri->AmbilAntrean(),
            'Tagihan' => DaftarBerhalaman::BuatDariData($halaman, array_map(fn (TagihanLangganan $tagihan): array => [
                ...TagihanLanggananTenant::PetakanTagihan($tagihan),
                'NamaTenant' => $namaTenant[$tagihan->IdTenant] ?? '—',
            ], $isi)),
            'Ringkasan' => $this->kueri->HitungRingkasan(),
            'Saring' => ['Kata' => $kata, 'Status' => $status === null ? '' : $status->value],
            'OpsiStatus' => array_map(fn (StatusTagihanLangganan $pilihan): array => ['Nilai' => $pilihan->value, 'Label' => $pilihan->AmbilLabel()], StatusTagihanLangganan::cases()),
        ]);
    }

    public function Tampilkan(string $tagihan): Response
    {
        $data = $this->kueri->CariTagihan($tagihan);
        abort_if($data === null, 404);
        $pembayaran = $this->kueri->AmbilPembayaranTagihan($data);
        $namaVerifikator = PenggunaPengelola::query()
            ->whereKey(array_values(array_filter(array_map(fn (PembayaranLangganan $baris): ?int => $baris->IdPenggunaPengelolaVerifikator, $pembayaran))))
            ->pluck('Nama', 'Id')
            ->all();

        return Inertia::render('Pengelola/Tagihan/Detail', [
            'Tagihan' => [
                ...TagihanLanggananTenant::PetakanTagihan($data),
                'NamaTenant' => $this->kueri->AmbilNamaTenant([$data->IdTenant])[$data->IdTenant] ?? '—',
            ],
            'Pembayaran' => array_map(fn (PembayaranLangganan $baris): array => [
                ...TagihanLanggananTenant::PetakanPembayaran($baris),
                'JumlahDiterima' => $baris->JumlahDiterima,
                'Verifikator' => $baris->IdPenggunaPengelolaVerifikator === null ? null : ($namaVerifikator[$baris->IdPenggunaPengelolaVerifikator] ?? null),
            ], $pembayaran),
        ]);
    }

    public function LihatBukti(string $pembayaran, BukaBuktiPembayaran $buka): StreamedResponse
    {
        $data = $buka->Jalankan($this->AmbilPelaku(), $pembayaran);
        abort_if($data === null || $data->PathBukti === null, 404);

        return Storage::disk((string) config('tagihan.DiskBukti'))->response($data->PathBukti, 'bukti-transfer.'.pathinfo($data->PathBukti, PATHINFO_EXTENSION), [
            'Content-Type' => $data->MimeBukti ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function Terima(string $pembayaran, TerimaPembayaranPermintaan $permintaan, TerimaPembayaranLangganan $terima): RedirectResponse
    {
        $terima->Jalankan($this->AmbilPelaku(), $pembayaran, $permintaan->AmbilJumlah(), $permintaan->AmbilCatatan());

        return back()->with('Kilat', 'Pembayaran diterima. Tagihan lunas dan langganan tenant aktif.');
    }

    public function Tolak(string $pembayaran, TolakPembayaranPermintaan $permintaan, TolakPembayaranLangganan $tolak): RedirectResponse
    {
        $tolak->Jalankan($this->AmbilPelaku(), $pembayaran, $permintaan->AmbilAlasan());

        return back()->with('Kilat', 'Pembayaran ditolak. Pemilik usaha diberi tahu lewat email.');
    }
}
