<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Organisasi\Model\Outlet;
use App\Domain\Penjualan\Data\DataKonteksPesanSendiri;
use App\Domain\Penjualan\Enum\JenisPemenuhanOnline;
use App\Domain\Penjualan\Enum\KanalPenjualan;
use App\Domain\Penjualan\Model\PengaturanTokoOnline;
use App\Domain\Penjualan\Model\ZonaPengiriman;

/** Hitung checkout sepenuhnya di server: harga kanal Online, promo/pajak, lalu ongkir zona kode pos. */
final class PenghitungTokoOnline
{
    public function __construct(private readonly PenghitungPesanSendiri $dasar) {}

    /**
     * @param  list<array{UuidProduk: string, Jumlah: int, Pilihan: list<string>, UuidVarian?: string|null}>  $baris
     * @return array<string, mixed>
     */
    public function Hitung(DataKonteksPesanSendiri $konteks, array $baris, JenisPemenuhanOnline $pemenuhan, ?string $kodePos): array
    {
        $hasil = $this->dasar->Hitung($konteks, $baris, KanalPenjualan::Online, true);
        $pengaturan = PengaturanTokoOnline::query()->firstOrFail();
        $outlet = Outlet::query()->findOrFail($konteks->idOutlet);
        $totalBarang = $hasil['Perkiraan']['Total'];

        if (($pemenuhan === JenisPemenuhanOnline::AmbilSendiri && ! $outlet->AmbilSendiriAktif)
            || ($pemenuhan === JenisPemenuhanOnline::Kirim && ! $outlet->KirimAktif)) {
            throw new PelanggaranAturanBisnis('PemenuhanTidakAktif', 'Cara menerima pesanan sedang tidak tersedia.', 'JenisPemenuhan');
        }

        if ($hasil['Subtotal']->Bandingkan(Uang::Dari($pengaturan->MinimalPesanan)) < 0) {
            throw new PelanggaranAturanBisnis('MinimalPesanan', 'Nilai pesanan belum mencapai minimal belanja.', 'Baris');
        }

        $zona = null;
        $ongkir = Uang::Nol();

        if ($pemenuhan === JenisPemenuhanOnline::Kirim) {
            if ($kodePos === null || preg_match('/^\d{5}$/', $kodePos) !== 1) {
                throw new PelanggaranAturanBisnis('KodePosWajib', 'Masukkan kode pos tujuan 5 digit.', 'KodePos');
            }

            $zona = ZonaPengiriman::query()
                ->where('IdOutlet', $konteks->idOutlet)
                ->where('Aktif', true)
                ->orderBy('Urutan')
                ->get()
                ->first(fn (ZonaPengiriman $z): bool => in_array($kodePos, $z->KodePos, true));

            if (! $zona instanceof ZonaPengiriman) {
                throw new PelanggaranAturanBisnis('DiLuarZona', 'Alamat ini belum masuk area pengiriman toko.', 'KodePos');
            }

            $gratis = $zona->GratisMulai !== null && $hasil['Subtotal']->Bandingkan(Uang::Dari($zona->GratisMulai)) >= 0;
            $ongkir = $gratis ? Uang::Nol() : Uang::Dari($zona->Ongkir);
        }

        return [
            ...$hasil,
            'Zona' => $zona,
            'Ongkir' => $ongkir,
            'Total' => $totalBarang->Tambah($ongkir),
        ];
    }
}
