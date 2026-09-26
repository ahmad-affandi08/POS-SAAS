<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Kelola;

use App\Domain\Organisasi\Aksi\SimpanOutlet;
use App\Domain\Organisasi\Aksi\UbahStatusOutlet;
use App\Domain\Organisasi\Enum\BentukMeja;
use App\Domain\Organisasi\Enum\JenisGudang;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use App\Domain\Organisasi\Kueri\MejaOutlet;
use App\Domain\Organisasi\Kueri\PemakaianBatasOrganisasi;
use App\Domain\Organisasi\Layanan\PenjagaModeMeja;
use App\Domain\Organisasi\Model\Gudang;
use App\Domain\Organisasi\Model\Merek;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Referensi\Enum\ZonaWaktu;
use App\Domain\Referensi\Kueri\WilayahKota;
use App\Domain\Tenant\Layanan\PastikanBatasPaket;
use App\Domain\Tenant\Layanan\PemeriksaFiturTenant;
use App\Http\Permintaan\Kelola\SimpanOutletPermintaan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Outlet & merek (F-02 langkah 1, BR-02.1, BR-02.2). Daftar menampilkan pemakaian batas paket.
 */
final class OutletKontroler extends DasarKelolaKontroler
{
    public function Daftar(WilayahKota $wilayahKota, PemakaianBatasOrganisasi $pemakaian, PastikanBatasPaket $batasPaket): Response
    {
        $boleh = $this->IdOutletBoleh();
        $kota = $this->PetakanKota($wilayahKota);
        $namaKota = array_column($kota, 'Nama', 'Kode');
        $jumlahGudang = Gudang::query()->where('Status', StatusOrganisasi::Aktif->value)->selectRaw('IdOutlet, count(*) as Jumlah')->groupBy('IdOutlet')->pluck('Jumlah', 'IdOutlet');
        $merek = Merek::query()->orderBy('Nama')->get();
        $namaMerek = $merek->pluck('Nama', 'Id');

        $outlet = Outlet::query()
            ->when($boleh !== null, fn ($kueri) => $kueri->whereKey($boleh ?? []))
            ->orderBy('Status')
            ->orderBy('Nama')
            ->get()
            ->map(fn (Outlet $baris): array => [
                'Uuid' => $baris->Uuid,
                'Kode' => $baris->Kode,
                'Nama' => $baris->Nama,
                'NamaMerek' => $namaMerek->get($baris->IdMerek),
                'NamaKota' => $baris->KodeKota === null ? null : ($namaKota[$baris->KodeKota] ?? $baris->KodeKota),
                'ZonaWaktu' => self::LabelZonaWaktu($baris->ZonaWaktu),
                'JamTutupBuku' => $baris->JamTutupBuku,
                'JumlahGudang' => (int) ($jumlahGudang->get($baris->Id) ?? 0),
                'Status' => $baris->Status->value,
            ]);
        $jumlahOutletPerMerek = Outlet::query()->selectRaw('IdMerek, count(*) as Jumlah')->groupBy('IdMerek')->pluck('Jumlah', 'IdMerek');

        return Inertia::render('Kelola/Outlet/Daftar', [
            'Outlet' => $outlet->values(),
            'Merek' => $merek->map(fn (Merek $baris): array => [
                'Uuid' => $baris->Uuid,
                'Nama' => $baris->Nama,
                'JumlahOutlet' => (int) ($jumlahOutletPerMerek->get($baris->Id) ?? 0),
            ])->values(),
            'Kota' => $kota,
            'BatasOutlet' => $batasPaket->AmbilRingkasan($this->IdTenant(), 'BatasOutlet', $pemakaian->HitungOutlet()),
        ]);
    }

    /** Halaman penuh "Tambah outlet" (pola sama dengan Tambah produk); batas paket tetap ditampilkan. */
    public function Buat(WilayahKota $wilayahKota, PemakaianBatasOrganisasi $pemakaian, PastikanBatasPaket $batasPaket): Response
    {
        return Inertia::render('Kelola/Outlet/Buat', [
            'Merek' => Merek::query()->orderBy('Nama')->get()->map(fn (Merek $merek): array => ['Nilai' => $merek->Uuid, 'Label' => $merek->Nama])->values(),
            'Kota' => $this->PetakanKota($wilayahKota),
            'BatasOutlet' => $batasPaket->AmbilRingkasan($this->IdTenant(), 'BatasOutlet', $pemakaian->HitungOutlet()),
        ]);
    }

    public function Detail(string $outlet, WilayahKota $wilayahKota, MejaOutlet $mejaOutlet, PenjagaModeMeja $modeMeja, PemeriksaFiturTenant $fitur): Response
    {
        $baris = $this->CariOutlet($outlet);

        return Inertia::render('Kelola/Outlet/Detail', [
            'Outlet' => [
                'Uuid' => $baris->Uuid,
                'Kode' => $baris->Kode,
                'Nama' => $baris->Nama,
                'UuidMerek' => Merek::query()->whereKey($baris->IdMerek)->value('Uuid'),
                'Alamat' => $baris->Alamat,
                'KodeKota' => $baris->KodeKota,
                'ZonaWaktu' => self::LabelZonaWaktu($baris->ZonaWaktu),
                'JamTutupBuku' => $baris->JamTutupBuku,
                'Pkp' => (bool) ($baris->ProfilPajak['Pkp'] ?? false),
                'Nitku' => is_string($baris->ProfilPajak['Nitku'] ?? null) ? $baris->ProfilPajak['Nitku'] : null,
                'PungutPbjt' => (bool) ($baris->ProfilPajak['PungutPbjt'] ?? false),
                'Status' => $baris->Status->value,
                'KodeTerkunci' => $baris->KodeDikunciPada !== null,
            ],
            'Gudang' => Gudang::query()
                ->where('IdOutlet', $baris->Id)
                ->orderBy('Status')
                ->orderBy('Nama')
                ->get()
                ->map(fn (Gudang $gudang): array => [
                    'Uuid' => $gudang->Uuid,
                    'Kode' => $gudang->Kode,
                    'Nama' => $gudang->Nama,
                    'Jenis' => $gudang->Jenis->value,
                    'Status' => $gudang->Status->value,
                ])
                ->values(),
            'Merek' => Merek::query()->orderBy('Nama')->get()->map(fn (Merek $merek): array => ['Nilai' => $merek->Uuid, 'Label' => $merek->Nama])->values(),
            'Kota' => $this->PetakanKota($wilayahKota),
            'JenisGudang' => array_map(fn (JenisGudang $jenis): array => ['Nilai' => $jenis->value, 'Label' => $jenis->AmbilLabel()], JenisGudang::cases()),
            // F-10a: bagian meja tampil bila fitur mode meja aktif di outlet ini atau sudah ada data meja.
            'ModeMeja' => ['Aktif' => $modeMeja->CekAktif($baris), ...$mejaOutlet->Ambil($baris->Id)],
            'BentukMeja' => array_map(fn (BentukMeja $bentuk): array => ['Nilai' => $bentuk->value, 'Label' => $bentuk->AmbilLabel()], BentukMeja::cases()),
            // F-17: pesan sendiri QR meja (sakelar outlet + fitur paket `kanal.self-order`).
            'PesanSendiri' => [
                'FiturAktif' => $fitur->CekAktifDiOutlet($baris->IdTenant, $baris->Id, PemeriksaFiturTenant::KUNCI_PESAN_SENDIRI),
                'Aktif' => $baris->PesanSendiriAktif,
            ],
        ]);
    }

    public function Simpan(SimpanOutletPermintaan $permintaan, SimpanOutlet $simpan): RedirectResponse
    {
        $outlet = $simpan->Jalankan(null, $permintaan->AmbilData());

        return redirect()->route('kelola.outlet.detail', ['outlet' => $outlet->Uuid])
            ->with('Kilat', "Outlet {$outlet->Nama} ditambahkan bersama lokasi stok Toko.");
    }

    public function Ubah(string $outlet, SimpanOutletPermintaan $permintaan, SimpanOutlet $simpan): RedirectResponse
    {
        $baris = $simpan->Jalankan($this->CariOutlet($outlet), $permintaan->AmbilData());

        return back()->with('Kilat', "Outlet {$baris->Nama} disimpan.");
    }

    public function Arsipkan(string $outlet, UbahStatusOutlet $ubahStatus): RedirectResponse
    {
        $baris = $ubahStatus->Jalankan($this->CariOutlet($outlet), StatusOrganisasi::Diarsipkan);

        return back()->with('Kilat', "Outlet {$baris->Nama} diarsipkan. Datanya tetap tersimpan dan bisa dipulihkan.");
    }

    public function Pulihkan(string $outlet, UbahStatusOutlet $ubahStatus): RedirectResponse
    {
        $baris = $ubahStatus->Jalankan($this->CariOutlet($outlet), StatusOrganisasi::Aktif);

        return back()->with('Kilat', "Outlet {$baris->Nama} aktif kembali.");
    }

    /**
     * @return list<array{Kode: string, Nama: string, NamaProvinsi: string|null, ZonaWaktu: string}>
     */
    private function PetakanKota(WilayahKota $wilayahKota): array
    {
        return array_map(fn (array $kota): array => [
            'Kode' => $kota['Kode'],
            'Nama' => $kota['Nama'],
            'NamaProvinsi' => $kota['NamaProvinsi'],
            'ZonaWaktu' => $kota['ZonaWaktu'],
        ], $wilayahKota->AmbilSemua());
    }

    /** Zona IANA yang disimpan → WIB/WITA/WIT untuk tampilan (§17.6.7). */
    private static function LabelZonaWaktu(string $zonaIana): string
    {
        foreach (ZonaWaktu::cases() as $zona) {
            if ($zona->AmbilZonaIana() === $zonaIana) {
                return $zona->value;
            }
        }

        return $zonaIana;
    }
}
