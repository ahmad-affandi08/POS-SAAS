<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Pos\V1;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Katalog\Kueri\InfoProdukStok;
use App\Domain\Organisasi\Data\DataAnggotaOutlet;
use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\OutletPenjualan;
use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Organisasi\Kueri\ZonaWaktuOutlet;
use App\Domain\Organisasi\Model\Perangkat;
use App\Domain\Pelanggan\Kueri\PelangganSalesman;
use App\Domain\Penjualan\Kueri\KunjunganSalesPos;
use App\Domain\Persediaan\Kueri\StokTersediaGudang;
use App\Http\Kontroler\Kontroler;
use App\Http\Perantara\AutentikasiPerangkat;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API aplikasi mode Salesman (Modul Salesman bagian 1, §9.7, SLS-11; §19 "Sales/Salesman"), dipakai online untuk
 * mengisi cache offline HP salesman. Pelaku = staf yang masuk dengan PIN di perangkat, dikirim di header `X-Id-Kasir`
 * (Uuid pengguna, §16.2); wajib anggota outlet perangkat dengan izin `salesman.kunjungan` (selain itu 403 `TanpaIzin`).
 *
 * - `GET salesman/pelanggan?kata=&halaman=` pelanggan aktif + posisi kredit (50 per halaman; nomor HP tersamar) dan
 *   `TerakhirDikunjungiPada`.
 * - `GET salesman/pelanggan/{uuid}/piutang` piutang terbuka pelanggan (kasir tempo & faktur grosir), urut jatuh tempo.
 * - `GET salesman/stok` jumlah tersedia per produk di lokasi stok Toko outlet perangkat (tanpa nilai/HPP).
 * - `GET salesman/kunjungan?tanggal=YYYY-MM-DD` kunjungan pelaku sendiri pada tanggal itu (bawaan hari ini, zona
 *   waktu outlet).
 *
 * Mutasi (pesanan & kunjungan) lewat outbox `sinkron/kirim`: `PesananGrosir.Buat` dan `Kunjungan.Catat`.
 */
final class SalesmanKontroler extends Kontroler
{
    public function __construct(private readonly AnggotaOutlet $anggota) {}

    public function Pelanggan(Request $permintaan, PelangganSalesman $kueri, KunjunganSalesPos $kunjungan, TanggalBisnisOutlet $tanggalBisnis): JsonResponse
    {
        $valid = $permintaan->validate([
            'kata' => ['nullable', 'string', 'max:60'],
            'halaman' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $this->AmbilPelaku($permintaan, $perangkat);
        $hasil = $kueri->Daftar((string) ($valid['kata'] ?? ''), (int) ($valid['halaman'] ?? 1), $tanggalBisnis->Hitung($perangkat->IdOutlet));
        $id = $kueri->PetaId(array_column($hasil['Data'], 'Uuid'));
        $terakhir = $kunjungan->AmbilTerakhir(array_values($id));

        return response()->json([
            'Pelanggan' => array_map(fn (array $p): array => [
                ...$p,
                'TerakhirDikunjungiPada' => isset($id[$p['Uuid']]) ? ($terakhir[$id[$p['Uuid']]] ?? null) : null,
            ], $hasil['Data']),
            'Halaman' => $hasil['Halaman'],
            'AdaBerikutnya' => $hasil['AdaBerikutnya'],
        ]);
    }

    public function Piutang(Request $permintaan, string $uuidPelanggan, PelangganSalesman $kueri, TanggalBisnisOutlet $tanggalBisnis): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $this->AmbilPelaku($permintaan, $perangkat);
        $idPelanggan = $kueri->CariId(strtoupper($uuidPelanggan));
        abort_if($idPelanggan === null, 404);

        return response()->json(['Piutang' => $kueri->PiutangTerbuka($idPelanggan, $tanggalBisnis->Hitung($perangkat->IdOutlet))]);
    }

    public function Stok(Request $permintaan, OutletPenjualan $outlet, StokTersediaGudang $stok, InfoProdukStok $infoProduk): JsonResponse
    {
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $this->AmbilPelaku($permintaan, $perangkat);
        $idGudang = $outlet->AmbilIdGudangToko($perangkat->IdOutlet);
        $saldo = $idGudang === null ? [] : $stok->Ambil($idGudang);
        $produk = $infoProduk->AmbilBanyak(array_keys($saldo));
        $baris = [];

        foreach ($saldo as $idProduk => $jumlah) {
            $info = $produk[$idProduk] ?? null;

            if ($info !== null && ! $info->dihapus && ! $info->diarsipkan) {
                $baris[] = ['UuidProduk' => $info->uuid, 'JumlahTersedia' => $jumlah];
            }
        }

        return response()->json([
            'Stok' => $baris,
            'DiambilPada' => CarbonImmutable::now()->utc()->toIso8601ZuluString(),
        ]);
    }

    public function Kunjungan(Request $permintaan, KunjunganSalesPos $kueri, ZonaWaktuOutlet $zona): JsonResponse
    {
        $valid = $permintaan->validate(['tanggal' => ['nullable', 'date_format:Y-m-d']]);
        $perangkat = AutentikasiPerangkat::AmbilPerangkat($permintaan);
        $pelaku = $this->AmbilPelaku($permintaan, $perangkat);
        $tanggal = is_string($valid['tanggal'] ?? null)
            ? CarbonImmutable::createFromFormat('!Y-m-d', $valid['tanggal'])
            : CarbonImmutable::now($zona->Ambil($perangkat->IdOutlet))->startOfDay();
        abort_if(! $tanggal instanceof CarbonImmutable, 422);

        return response()->json(['Tanggal' => $tanggal->format('Y-m-d'), 'Kunjungan' => $kueri->DaftarHarian($pelaku->id, $tanggal)]);
    }

    /** Staf dari header `X-Id-Kasir`: anggota outlet perangkat dengan izin `salesman.kunjungan`. */
    private function AmbilPelaku(Request $permintaan, Perangkat $perangkat): DataAnggotaOutlet
    {
        $uuid = strtoupper(trim((string) $permintaan->header('X-Id-Kasir', '')));
        $pelaku = preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $uuid) === 1 ? $this->anggota->Cari($perangkat->IdTenant, $uuid, $perangkat->IdOutlet) : null;

        if ($pelaku === null || ! $pelaku->CekIzin(IzinTenant::SalesmanKunjungan->value)) {
            throw new PelanggaranAturanBisnis('TanpaIzin', 'Pengguna ini tidak punya izin salesman di outlet ini.', 'X-Id-Kasir', 403);
        }

        return $pelaku;
    }
}
