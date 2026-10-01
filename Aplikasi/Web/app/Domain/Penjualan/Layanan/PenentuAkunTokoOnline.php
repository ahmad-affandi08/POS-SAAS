<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Layanan;

use App\Domain\Integrasi\Whatsapp\PembuatPengirimWhatsapp;
use App\Domain\Penjualan\Model\PengaturanTokoOnline;

/**
 * F-17 bagian 3: tombol "Masuk" toko online hanya tampil bila toko online aktif, tenant tidak mematikan akun pembeli
 * (`PengaturanTokoOnline.AkunPelangganAktif`), **dan** platform punya pengirim WhatsApp aktif (P-05). Tanpa WhatsApp
 * kode masuk tidak bisa sampai, jadi menampilkan tombolnya hanya membuat pembeli menunggu kode yang tidak datang;
 * checkout tamu tetap berjalan seperti biasa.
 */
final class PenentuAkunTokoOnline
{
    public function __construct(private readonly PembuatPengirimWhatsapp $whatsapp) {}

    public function CekAktif(): bool
    {
        $atur = PengaturanTokoOnline::query()->first();

        return $atur !== null && $atur->Aktif && $atur->AkunPelangganAktif && $this->CekWhatsappTersedia();
    }

    public function CekWhatsappTersedia(): bool
    {
        return $this->whatsapp->AmbilAktif() !== null;
    }
}
