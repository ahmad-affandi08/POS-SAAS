<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\OutletPenjualan;
use App\Domain\Organisasi\Kueri\ProfilPajakOutlet;
use App\Domain\Pelanggan\Kueri\IdentitasPelanggan;
use App\Domain\Penjualan\Enum\StatusPenjualan;
use App\Domain\Penjualan\Model\Penjualan;
use App\Domain\Penjualan\Model\PenjualanDetail;
use App\Domain\Penjualan\Model\PenjualanPajak;
use App\Domain\Penjualan\Model\PenjualanPembayaran;
use App\Domain\Penjualan\Model\ReturPenjualan;
use App\Domain\Tenant\Kueri\PengaturanStrukTenant;
use App\Domain\Tenant\Kueri\ProfilTenant;

/**
 * Isi struk digital publik (POS-11, `/s/{kodeStruk}`) untuk satu penjualan tenant aktif: hanya data yang juga tercetak
 * di struk (tanpa HPP, catatan internal, atau tinjauan), mengikuti pengaturan struk tenant (alamat, NPWP, kasir,
 * pelanggan, catatan kaki). Penjualan yang di-void ditandai; retur disebut total pengembaliannya. Null bila tidak ada
 * atau struk digital dimatikan.
 */
final class StrukDigitalPenjualan
{
    public function __construct(
        private readonly PengaturanStrukTenant $pengaturan,
        private readonly ProfilTenant $profil,
        private readonly AnggotaOutlet $anggota,
        private readonly IdentitasPelanggan $pelanggan,
        private readonly OutletPenjualan $outlet,
        private readonly ProfilPajakOutlet $pajakOutlet,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function Ambil(int $idTenant, string $uuid): ?array
    {
        $struk = $this->pengaturan->Ambil($idTenant);
        $p = $struk->tampilkanStrukDigital ? Penjualan::query()->where('Uuid', $uuid)->first() : null;

        if ($p === null) {
            return null;
        }

        $tenant = $this->profil->Ambil($idTenant);
        $outlet = $this->outlet->Ambil($p->IdOutlet, $p->IdPerangkat);
        $pkp = $this->pajakOutlet->Ambil($p->IdOutlet)->pkp ?? false;
        $totalRetur = null;

        foreach (ReturPenjualan::query()->where('IdPenjualanAsal', $p->Id)->pluck('TotalRefund') as $refund) {
            $totalRetur = ($totalRetur ?? Uang::Nol())->Tambah(Uang::Dari((string) $refund));
        }

        return [
            'NamaUsaha' => $struk->namaDicetak ?? $tenant['Nama'],
            'TeksKepala' => $struk->teksKepala,
            'NamaOutlet' => $outlet?->namaOutlet,
            'Alamat' => $struk->tampilkanAlamat ? $outlet?->alamat : null,
            'Npwp' => $struk->tampilkanNpwp && $pkp ? $tenant['Npwp'] : null,
            'Nomor' => $p->Nomor,
            'Waktu' => $p->DibuatOfflinePada->toIso8601String(),
            'NamaKasir' => $struk->tampilkanKasir ? ($this->anggota->AmbilNama([$p->IdPengguna])[$p->IdPengguna]['Nama'] ?? null) : null,
            'NamaPelanggan' => $struk->tampilkanPelanggan ? ($this->pelanggan->AmbilRingkas($p->IdPelanggan)['Nama'] ?? null) : null,
            'Dibatalkan' => $p->Status === StatusPenjualan::Void,
            'Baris' => array_values(PenjualanDetail::query()->where('IdPenjualan', $p->Id)->orderBy('Urutan')->get()->map(fn (PenjualanDetail $d): array => [
                'NamaProduk' => $d->NamaProduk,
                'Pilihan' => array_values(array_map(fn (array $pilihan): string => $pilihan['Nama'], $d->Pilihan ?? [])),
                'Jumlah' => $d->Jumlah,
                'HargaSatuan' => $d->HargaSatuan,
                'Diskon' => $d->JumlahDiskon,
                'Total' => $d->Bruto,
            ])->all()),
            'Subtotal' => $p->Subtotal,
            'TotalDiskon' => $p->TotalDiskon,
            'BiayaLayanan' => $p->BiayaLayanan,
            'Pajak' => array_values(PenjualanPajak::query()->where('IdPenjualan', $p->Id)->orderBy('Id')->get()->map(fn (PenjualanPajak $pajak): array => [
                'Kode' => $pajak->KodeJenisPajak,
                'Tarif' => $pajak->Tarif,
                'Jumlah' => $pajak->Jumlah,
            ])->all()),
            'Pembulatan' => $p->Pembulatan,
            'TotalAkhir' => $p->TotalAkhir,
            'Pembayaran' => array_values(PenjualanPembayaran::query()->where('IdPenjualan', $p->Id)->orderBy('Urutan')->get()->map(fn (PenjualanPembayaran $b): array => [
                'NamaMetode' => $b->NamaMetode,
                'Jumlah' => $b->Jumlah,
            ])->all()),
            'Kembalian' => $p->Kembalian,
            'TotalRetur' => $totalRetur?->KeString(),
            'CatatanKaki' => $struk->catatanKaki,
            'TeksPenutup' => $struk->teksPenutup,
        ];
    }
}
