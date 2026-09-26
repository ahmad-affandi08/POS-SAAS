<?php

declare(strict_types=1);

namespace App\Domain\Katalog\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Katalog\Enum\JenisProduk;
use App\Domain\Katalog\Model\PaketSesi;
use App\Domain\Katalog\Model\PaketSesiProduk;
use App\Domain\Katalog\Model\Produk;
use Illuminate\Support\Facades\DB;

/**
 * Simpan definisi paket sesi (F-16d bagian 2, CRM-04; izin `produk.kelola`, fitur `pelanggan.paket-sesi`). Produk paket
 * wajib berjenis Jasa, satu definisi per produk. Produk yang boleh ditukar: daftar produk Jasa lain, atau semua produk
 * Jasa. Mengubah definisi hanya berlaku untuk penjualan berikutnya; saldo sesi yang sudah terjual tetap memakai jumlah
 * sesi & masa berlaku saat dibeli. Audit `katalog.paket-sesi-simpan`.
 */
final class SimpanPaketSesi
{
    public const MAKS_SESI = 1000;

    public const MAKS_HARI = 3650;

    public function __construct(private readonly PencatatAudit $audit) {}

    /**
     * @param  list<string>  $uuidProdukBerlaku
     */
    public function Jalankan(
        ?PaketSesi $paket,
        string $uuidProduk,
        int $jumlahSesi,
        ?int $masaBerlakuHari,
        bool $semuaProdukJasa,
        array $uuidProdukBerlaku,
        bool $aktif,
        int $idPengguna,
    ): PaketSesi {
        if ($jumlahSesi < 1 || $jumlahSesi > self::MAKS_SESI) {
            throw new PelanggaranAturanBisnis('JumlahSesiTidakValid', 'Jumlah sesi antara 1 sampai '.self::MAKS_SESI.'.', 'JumlahSesi');
        }

        if ($masaBerlakuHari !== null && ($masaBerlakuHari < 1 || $masaBerlakuHari > self::MAKS_HARI)) {
            throw new PelanggaranAturanBisnis('MasaBerlakuTidakValid', 'Masa berlaku antara 1 sampai '.self::MAKS_HARI.' hari, atau kosongkan untuk tanpa batas.', 'MasaBerlakuHari');
        }

        $produk = Produk::query()->where('Uuid', $uuidProduk)->first()
            ?? throw new PelanggaranAturanBisnis('ProdukTidakDikenal', 'Produk paket tidak ditemukan.', 'UuidProduk');

        if ($produk->Jenis !== JenisProduk::Jasa) {
            throw new PelanggaranAturanBisnis('ProdukBukanJasa', "Paket sesi harus produk berjenis Jasa; {$produk->Nama} berjenis {$produk->Jenis->AmbilLabel()}.", 'UuidProduk');
        }

        $berlaku = $semuaProdukJasa ? [] : $this->AmbilProdukBerlaku($uuidProdukBerlaku, $produk->Id);

        if (! $semuaProdukJasa && $berlaku === []) {
            throw new PelanggaranAturanBisnis('ProdukBerlakuWajib', 'Pilih minimal satu layanan yang bisa ditukar dengan sesi paket, atau pilih semua layanan.', 'ProdukBerlaku');
        }

        return DB::transaction(function () use ($paket, $produk, $jumlahSesi, $masaBerlakuHari, $semuaProdukJasa, $berlaku, $aktif, $idPengguna): PaketSesi {
            $lain = PaketSesi::query()->where('IdProduk', $produk->Id)->when($paket !== null, fn ($k) => $k->whereKeyNot($paket?->Id))->exists();

            if ($lain) {
                throw new PelanggaranAturanBisnis('PaketSesiSudahAda', "{$produk->Nama} sudah punya definisi paket sesi.", 'UuidProduk');
            }

            $lama = $paket?->only(['IdProduk', 'JumlahSesi', 'MasaBerlakuHari', 'SemuaProdukJasa', 'Aktif']);
            $paket ??= new PaketSesi;
            $paket->fill([
                'IdProduk' => $produk->Id,
                'JumlahSesi' => $jumlahSesi,
                'MasaBerlakuHari' => $masaBerlakuHari,
                'SemuaProdukJasa' => $semuaProdukJasa,
                'Aktif' => $aktif,
            ]);
            $paket->save();
            // Katalog POS delta memakai `Produk.DiubahPada`: produk paket (lama & baru) disentuh agar ikut terkirim.
            Produk::query()->whereIn('Id', array_unique(array_filter([$produk->Id, $lama['IdProduk'] ?? null])))->update(['DiubahPada' => now()]);

            PaketSesiProduk::query()->where('IdPaketSesi', $paket->Id)->whereNotIn('IdProduk', $berlaku === [] ? [0] : $berlaku)->delete();

            foreach ($berlaku as $idProduk) {
                PaketSesiProduk::query()->firstOrCreate(['IdPaketSesi' => $paket->Id, 'IdProduk' => $idProduk]);
            }

            $this->audit->Catat('katalog.paket-sesi-simpan', $paket, $lama, [
                'Produk' => $produk->Nama,
                'JumlahSesi' => $jumlahSesi,
                'MasaBerlakuHari' => $masaBerlakuHari,
                'SemuaProdukJasa' => $semuaProdukJasa,
                'JumlahProdukBerlaku' => count($berlaku),
                'Aktif' => $aktif,
            ], idPengguna: $idPengguna);

            return $paket;
        });
    }

    /**
     * @param  list<string>  $uuid
     * @return list<int>
     */
    private function AmbilProdukBerlaku(array $uuid, int $idProdukPaket): array
    {
        $uuid = array_values(array_unique($uuid));

        if ($uuid === []) {
            return [];
        }

        $produk = Produk::query()->whereIn('Uuid', $uuid)->get(['Id', 'Nama', 'Jenis']);

        if ($produk->count() !== count($uuid)) {
            throw new PelanggaranAturanBisnis('ProdukTidakDikenal', 'Sebagian layanan yang dipilih tidak ditemukan.', 'ProdukBerlaku');
        }

        foreach ($produk as $p) {
            if ($p->Jenis !== JenisProduk::Jasa || $p->Id === $idProdukPaket) {
                throw new PelanggaranAturanBisnis('ProdukBerlakuTidakValid', "{$p->Nama} tidak bisa ditukar dengan sesi: pilih produk Jasa selain produk paketnya.", 'ProdukBerlaku');
            }
        }

        return array_values(array_map(fn (Produk $p): int => $p->Id, $produk->all()));
    }
}
