<?php

declare(strict_types=1);

namespace Tests\Pendukung\Kasir;

use App\Domain\Akuntansi\Model\Akun;
use App\Domain\Kasir\Enum\JenisKategoriKas;
use App\Domain\Kasir\Model\KategoriKas;
use App\Domain\Organisasi\Enum\PeranTenantBawaan;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\Perangkat;
use App\Domain\Tenant\Model\Tenant;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Pendukung\Organisasi\BantuanOrganisasi;
use Tests\Pendukung\Organisasi\BantuanPerangkat;
use Tests\Pendukung\Persediaan\BantuanPersediaan;
use Tests\TestCase;

/**
 * Prasyarat test F-06 shift & kas: tenant ber-COA template (Kas Outlet 1-1100, Kas Brankas 1-1150), satu perangkat
 * kasir aktif, anggota Kasir & Supervisor, kategori kas keluar (beban lain-lain) dan masuk (pendapatan lain), serta
 * pembuat item outbox. Panggil `BantuanPendaftaran::SiapkanPrasyarat()` dulu.
 */
final class BantuanKasir
{
    /**
     * @return array{Tenant: Tenant, Pemilik: Pengguna, Outlet: Outlet, Perangkat: Perangkat, Token: string, Kasir: Pengguna, Supervisor: Pengguna, KategoriKeluar: KategoriKas, KategoriMasuk: KategoriKas}
     */
    public static function Siapkan(TestCase $tes, string $namaUsaha = 'Kopi Senja Solo'): array
    {
        $t = BantuanPersediaan::SiapkanTenant($namaUsaha);
        $perangkat = BantuanPerangkat::BuatDanAktifkan($tes, $t['Tenant']->Id, $t['Outlet']);
        BantuanOrganisasi::AturKonteks($t['Tenant']->Id);
        $kasir = BantuanOrganisasi::TambahAnggota($t['Tenant']->Id, PeranTenantBawaan::Kasir);
        $supervisor = BantuanOrganisasi::TambahAnggota($t['Tenant']->Id, PeranTenantBawaan::Supervisor);

        return [
            'Tenant' => $t['Tenant'],
            'Pemilik' => $t['Pemilik'],
            'Outlet' => $t['Outlet'],
            'Perangkat' => $perangkat['Perangkat'],
            'Token' => $perangkat['Token'],
            'Kasir' => $kasir,
            'Supervisor' => $supervisor,
            'KategoriKeluar' => self::BuatKategori('Beli es batu & galon', JenisKategoriKas::Keluar, '6-9000'),
            'KategoriMasuk' => self::BuatKategori('Tambahan uang receh', JenisKategoriKas::Masuk, '4-9000'),
        ];
    }

    public static function BuatKategori(string $nama, JenisKategoriKas $jenis, string $kodeAkun, bool $aktif = true): KategoriKas
    {
        return KategoriKas::query()->create([
            'Nama' => $nama,
            'Jenis' => $jenis,
            'IdAkun' => Akun::query()->where('Kode', $kodeAkun)->value('Id'),
            'Aktif' => $aktif,
        ]);
    }

    public static function Uuid(): string
    {
        return (string) Str::ulid();
    }

    /**
     * @param  array<string, mixed>  $timpa
     * @return array{Jenis: string, Uuid: string, Data: array<string, mixed>}
     */
    public static function ItemBukaShift(Pengguna $kasir, string $kasAwal = '500000.00', array $timpa = [], ?string $uuid = null): array
    {
        return [
            'Jenis' => 'Shift.Buka',
            'Uuid' => $uuid ?? self::Uuid(),
            'Data' => array_merge([
                'UuidPengguna' => $kasir->Uuid,
                'DibukaPada' => now()->subMinutes(30)->utc()->toIso8601ZuluString(),
                'KasAwal' => $kasAwal,
            ], $timpa),
        ];
    }

    /**
     * @param  array<string, mixed>  $timpa
     * @return array{Jenis: string, Uuid: string, Data: array<string, mixed>}
     */
    public static function ItemMutasiKas(string $uuidShift, Pengguna $pencatat, string $jenis, string $jumlah, ?KategoriKas $kategori, array $timpa = [], ?string $uuid = null): array
    {
        return [
            'Jenis' => 'MutasiKas.Catat',
            'Uuid' => $uuid ?? self::Uuid(),
            'Data' => array_merge([
                'UuidShift' => $uuidShift,
                'Jenis' => $jenis,
                'UuidKategori' => $kategori?->Uuid,
                'Jumlah' => $jumlah,
                'Catatan' => 'Es batu 3 karung untuk bar',
                'UuidPencatat' => $pencatat->Uuid,
                'DicatatPada' => now()->subMinutes(10)->utc()->toIso8601ZuluString(),
            ], $timpa),
        ];
    }

    /**
     * @param  list<array{Jenis: string, Uuid: string, Data: array<string, mixed>}>  $item
     * @return TestResponse<Response>
     */
    public static function Kirim(TestCase $tes, string $token, array $item): TestResponse
    {
        return $tes->withToken($token)->postJson('/api/pos/v1/sinkron/kirim', ['Item' => $item]);
    }

    /**
     * Kirim lalu kembalikan `[Status, Kode galat|null]` per item.
     *
     * @param  list<array{Jenis: string, Uuid: string, Data: array<string, mixed>}>  $item
     * @return list<array{0: string, 1: string|null}>
     */
    public static function KirimRingkas(TestCase $tes, string $token, array $item): array
    {
        $hasil = self::Kirim($tes, $token, $item)->assertOk()->json('Hasil');

        $ringkas = [];

        foreach (is_array($hasil) ? $hasil : [] as $h) {
            $kode = is_array($h) && is_array($h['Galat'] ?? null) ? ($h['Galat']['Kode'] ?? null) : null;
            $ringkas[] = [is_array($h) ? (string) ($h['Status'] ?? '') : '', is_string($kode) ? $kode : null];
        }

        return $ringkas;
    }
}
