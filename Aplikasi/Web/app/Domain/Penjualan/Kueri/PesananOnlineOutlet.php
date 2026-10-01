<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Organisasi\Kueri\TanggalBisnisOutlet;
use App\Domain\Pelanggan\Kueri\CariPelangganPos;
use App\Domain\Penjualan\Enum\JenisMetodePembayaran;
use App\Domain\Penjualan\Enum\StatusPesananOnline;
use App\Domain\Penjualan\Model\MetodePembayaran;
use App\Domain\Penjualan\Model\PesananOnline;
use App\Domain\Penjualan\Model\PesananOnlineDetail;

/**
 * Pesanan online aktif untuk dimuat kasir ke keranjang POS; belum menyentuh stok/jurnal.
 *
 * F-17 bagian 2: `SisaUangMuka` adalah uang pelanggan yang sudah diterima (J-17.1) dan belum dipakai penjualan mana
 * pun. Kasir memakainya sebagai baris bayar **Uang muka** dengan `UuidPesananOnline`, persis seperti pre-order F-12,
 * sehingga pesanan berbayar tidak ditagihkan dua kali.
 *
 * F-17 bagian 3: `Pelanggan` (null untuk tamu) = pembeli yang masuk dengan kode WhatsApp, dalam bentuk yang sama
 * dengan hasil cari pelanggan POS (`GET /pelanggan`), supaya kasir memasangnya ke keranjang tanpa mencarinya lagi:
 * poin, tier, dan promo pelanggan berlaku seperti belanja di toko. Kontrak hanya bertambah (kompatibel mundur).
 */
final class PesananOnlineOutlet
{
    public function __construct(
        private readonly CariPelangganPos $pelanggan,
        private readonly TanggalBisnisOutlet $tanggal,
    ) {}

    /**
     * @return array{Pesanan: list<array<string, mixed>>, TanggalBisnis: string, MetodeUangMuka: array{Uuid: string, Nama: string}|null}
     */
    public function AmbilAktif(int $idOutlet): array
    {
        $daftar = PesananOnline::query()->with('Detail')->where('IdOutlet', $idOutlet)
            ->whereIn('Status', [StatusPesananOnline::Dikonfirmasi->value, StatusPesananOnline::Diproses->value, StatusPesananOnline::Siap->value])
            ->whereNull('IdPenjualan')->orderBy('DibuatPada')->get();
        $tanggalBisnis = $this->tanggal->Hitung($idOutlet)->toDateString();
        $pelanggan = $this->pelanggan->AmbilPerId(array_values(array_filter($daftar->pluck('IdPelanggan')->all(), 'is_int')), $tanggalBisnis);
        $hasil = [];

        foreach ($daftar as $p) {
            $hasil[] = [
                'Uuid' => $p->Uuid, 'Nomor' => $p->Nomor, 'NamaPelanggan' => $p->NamaPelanggan,
                'JenisPemenuhan' => $p->JenisPemenuhan->value, 'MetodePembayaran' => $p->MetodePembayaran->value,
                'Status' => $p->Status->value, 'Subtotal' => $p->Subtotal, 'Ongkir' => $p->Ongkir, 'DiskonOngkir' => $p->DiskonOngkir, 'Total' => $p->Total,
                'Catatan' => $p->Catatan, 'DibuatPada' => $p->DibuatPada?->toIso8601ZuluString(),
                'SudahDibayar' => $p->DibayarPada !== null, 'SisaUangMuka' => $p->AmbilSisaUangMuka()->KeString(),
                'Pelanggan' => $p->IdPelanggan === null ? null : ($pelanggan[$p->IdPelanggan] ?? null),
                'Baris' => $p->Detail->map(fn (PesananOnlineDetail $d): array => [
                    'UuidProduk' => $d->UuidProduk, 'UuidProdukSatuan' => $d->UuidProdukSatuan,
                    'NamaProduk' => $d->NamaProduk, 'Jumlah' => $d->Jumlah, 'HargaSatuan' => $d->HargaSatuan,
                    'HargaPilihan' => $d->HargaPilihan, 'Pilihan' => $d->Pilihan ?? [], 'Catatan' => $d->Catatan,
                ])->values()->all(),
            ];
        }

        $metode = MetodePembayaran::query()->where('Jenis', JenisMetodePembayaran::UangMuka->value)->first(['Uuid', 'Nama']);

        return [
            'Pesanan' => $hasil,
            // Acuan hitungan harian `Pelanggan.PemakaianPromo` (sama dengan `GET /pelanggan`).
            'TanggalBisnis' => $tanggalBisnis,
            'MetodeUangMuka' => $metode === null ? null : ['Uuid' => $metode->Uuid, 'Nama' => $metode->Nama],
        ];
    }
}
