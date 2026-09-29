<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Kueri\JurnalSumber;
use App\Domain\Bersama\Dokumen\Model\RiwayatStatusDokumen;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Kueri\DaftarAnggota;
use App\Domain\Organisasi\Kueri\PetaUuidOutlet;
use App\Domain\Pelanggan\Kueri\IdentitasPelanggan;
use App\Domain\Pelanggan\Layanan\PencatatPiutangPenjualan;
use App\Domain\Penjualan\Enum\StatusDokumenGrosir;
use App\Domain\Penjualan\Model\FakturPenjualan;
use App\Domain\Penjualan\Model\PesananGrosir;
use App\Domain\Penjualan\Model\PesananGrosirDetail;
use App\Domain\Penjualan\Model\SuratJalan;
use App\Domain\Penjualan\Model\SuratJalanDetail;

/**
 * Detail dokumen grosir untuk halaman back-office (F-12, §9.7): header, baris, dokumen terkait, jurnal
 * (`JurnalSumber`), dan riwayat status. Uang & jumlah tetap string desimal (CLAUDE.md #7).
 */
final class DetailGrosir
{
    public function __construct(
        private readonly IdentitasPelanggan $identitas,
        private readonly PetaUuidOutlet $petaOutlet,
        private readonly PencatatPiutangPenjualan $piutang,
        private readonly JurnalSumber $jurnal,
        private readonly DaftarAnggota $anggota,
        private readonly KonteksTenant $konteks,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function Pesanan(PesananGrosir $pesanan): array
    {
        $baris = PesananGrosirDetail::query()->where('IdPesananGrosir', $pesanan->Id)->orderBy('Urutan')->get();

        return [
            'Pesanan' => [
                ...$this->Header($pesanan->Uuid, $pesanan->Nomor, $pesanan->IdPelanggan, $pesanan->IdOutlet),
                'Tanggal' => $pesanan->Tanggal->format('Y-m-d'),
                'TanggalKirimDiminta' => $pesanan->TanggalKirimDiminta?->format('Y-m-d'),
                'Status' => $pesanan->Status->value,
                'LabelStatus' => $pesanan->Status->AmbilLabel(),
                'TerminHari' => $pesanan->TerminHari,
                'TarifPpn' => $pesanan->TarifPpn,
                'Subtotal' => $pesanan->Subtotal,
                'Diskon' => $pesanan->Diskon,
                'DasarPengenaanPajak' => $pesanan->DasarPengenaanPajak,
                'Pajak' => $pesanan->Pajak,
                'Total' => $pesanan->Total,
                'Catatan' => $pesanan->Catatan,
                'AlasanPersetujuanKredit' => $pesanan->AlasanPersetujuanKredit,
                'AlasanBatal' => $pesanan->AlasanBatal,
                'BolehDiubah' => $pesanan->Status->CekBolehDiubah(),
                'BolehDikirim' => $pesanan->Status->CekBolehDikirim(),
            ],
            'Baris' => array_values($baris->map(fn (PesananGrosirDetail $d): array => [
                'Urutan' => $d->Urutan,
                'NamaProduk' => $d->NamaProduk,
                'Sku' => $d->Sku,
                'SimbolSatuan' => $d->SimbolSatuan,
                'Jumlah' => $d->Jumlah,
                'JumlahTerkirim' => $d->JumlahTerkirim,
                'SisaKirim' => $d->AmbilSisaKirim()->KeString(),
                'Harga' => $d->Harga,
                'Diskon' => $d->Diskon,
                'Subtotal' => $d->Subtotal,
            ])->all()),
            'SuratJalan' => array_values(SuratJalan::query()->where('IdPesananGrosir', $pesanan->Id)->orderBy('Id')->get()->map(
                fn (SuratJalan $sj): array => [
                    'Uuid' => $sj->Uuid,
                    'Nomor' => $sj->Nomor,
                    'Tanggal' => $sj->Tanggal->format('Y-m-d'),
                    'Status' => $sj->Status->value,
                    'LabelStatus' => $sj->Status->AmbilLabel(),
                    'Total' => $sj->Total,
                    'Difakturkan' => $sj->IdFakturPenjualan !== null,
                ],
            )->all()),
            'Riwayat' => $this->Riwayat(PesananGrosir::JENIS_DOKUMEN, $pesanan->Id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function SuratJalan(SuratJalan $suratJalan): array
    {
        $baris = SuratJalanDetail::query()->where('IdSuratJalan', $suratJalan->Id)->orderBy('Urutan')->get();
        $pesanan = PesananGrosir::query()->whereKey($suratJalan->IdPesananGrosir)->first();
        $faktur = $suratJalan->IdFakturPenjualan === null ? null : FakturPenjualan::query()->whereKey($suratJalan->IdFakturPenjualan)->first();

        return [
            'SuratJalan' => [
                ...$this->Header($suratJalan->Uuid, $suratJalan->Nomor, $suratJalan->IdPelanggan, $suratJalan->IdOutlet),
                'Tanggal' => $suratJalan->Tanggal->format('Y-m-d'),
                'Status' => $suratJalan->Status->value,
                'LabelStatus' => $suratJalan->Status->AmbilLabel(),
                'TarifPpn' => $suratJalan->TarifPpn,
                'Subtotal' => $suratJalan->Subtotal,
                'Diskon' => $suratJalan->Diskon,
                'DasarPengenaanPajak' => $suratJalan->DasarPengenaanPajak,
                'Pajak' => $suratJalan->Pajak,
                'Total' => $suratJalan->Total,
                'TotalHpp' => $suratJalan->TotalHpp,
                'NamaPengirim' => $suratJalan->NamaPengirim,
                'NomorKendaraan' => $suratJalan->NomorKendaraan,
                'NamaPenerima' => $suratJalan->NamaPenerima,
                'Catatan' => $suratJalan->Catatan,
                'AlasanBatal' => $suratJalan->AlasanBatal,
                'NomorPesanan' => $pesanan?->Nomor,
                'UuidPesanan' => $pesanan?->Uuid,
                'NomorFaktur' => $faktur?->Nomor,
                'UuidFaktur' => $faktur?->Uuid,
                'BolehDibatalkan' => $suratJalan->Status === StatusDokumenGrosir::Diposting && $suratJalan->IdFakturPenjualan === null,
            ],
            'Baris' => array_values($baris->map(fn (SuratJalanDetail $d): array => [
                'Urutan' => $d->Urutan,
                'NamaProduk' => $d->NamaProduk,
                'Sku' => $d->Sku,
                'SimbolSatuan' => $d->SimbolSatuan,
                'Jumlah' => $d->Jumlah,
                'Harga' => $d->Harga,
                'Diskon' => $d->Diskon,
                'Subtotal' => $d->Subtotal,
                'HppSatuan' => $d->HppSatuan,
                'TotalHpp' => $d->TotalHpp,
            ])->all()),
            'Jurnal' => $this->jurnal->Ambil(JenisSumberJurnal::SuratJalan, $suratJalan->Id),
            'Riwayat' => $this->Riwayat(SuratJalan::JENIS_DOKUMEN, $suratJalan->Id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function Faktur(FakturPenjualan $faktur): array
    {
        $piutang = $this->piutang->AmbilPiutangBanyakFaktur([$faktur->Id])[$faktur->Id] ?? null;

        return [
            'Faktur' => [
                ...$this->Header($faktur->Uuid, $faktur->Nomor, $faktur->IdPelanggan, $faktur->IdOutlet),
                'Tanggal' => $faktur->Tanggal->format('Y-m-d'),
                'JatuhTempo' => $faktur->JatuhTempo->format('Y-m-d'),
                'Status' => $faktur->Status->value,
                'LabelStatus' => $faktur->Status->AmbilLabel(),
                'TerminHari' => $faktur->TerminHari,
                'PeriodePenyerahan' => $faktur->PeriodePenyerahan,
                'NomorFakturPajak' => $faktur->NomorFakturPajak,
                'TarifPpn' => $faktur->TarifPpn,
                'Subtotal' => $faktur->Subtotal,
                'Diskon' => $faktur->Diskon,
                'DasarPengenaanPajak' => $faktur->DasarPengenaanPajak,
                'Pajak' => $faktur->Pajak,
                'Total' => $faktur->Total,
                'Catatan' => $faktur->Catatan,
                'AlasanBatal' => $faktur->AlasanBatal,
                'SisaPiutang' => $piutang['Sisa'] ?? null,
                'StatusPiutang' => $piutang['Status'] ?? null,
                'LabelStatusPiutang' => $piutang['LabelStatus'] ?? null,
            ],
            'SuratJalan' => array_values(SuratJalan::query()->where('IdFakturPenjualan', $faktur->Id)->orderBy('Tanggal')->orderBy('Id')->get()->map(
                fn (SuratJalan $sj): array => [
                    'Uuid' => $sj->Uuid,
                    'Nomor' => $sj->Nomor,
                    'Tanggal' => $sj->Tanggal->format('Y-m-d'),
                    'Subtotal' => $sj->Subtotal,
                    'Diskon' => $sj->Diskon,
                    'Pajak' => $sj->Pajak,
                    'Total' => $sj->Total,
                ],
            )->all()),
            'Jurnal' => $this->jurnal->Ambil(JenisSumberJurnal::FakturPenjualan, $faktur->Id),
            'Riwayat' => $this->Riwayat(FakturPenjualan::JENIS_DOKUMEN, $faktur->Id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function Header(string $uuid, string $nomor, int $idPelanggan, int $idOutlet): array
    {
        $pelanggan = $this->identitas->AmbilNamaBanyak([$idPelanggan])[$idPelanggan] ?? null;

        return [
            'Uuid' => $uuid,
            'Nomor' => $nomor,
            'NamaPelanggan' => $pelanggan['Nama'] ?? '',
            'UuidPelanggan' => $pelanggan['Uuid'] ?? null,
            'KodeOutlet' => $this->petaOutlet->AmbilKode([$idOutlet])[$idOutlet] ?? '',
        ];
    }

    /**
     * @return list<array{StatusKe: string, Oleh: string|null, Pada: string, Alasan: string|null}>
     */
    private function Riwayat(string $jenis, int $id): array
    {
        $riwayat = RiwayatStatusDokumen::query()->where('JenisDokumen', $jenis)->where('IdDokumen', $id)->orderBy('Id')->get();
        $idPengguna = array_values(array_unique(array_filter($riwayat->pluck('DiubahOleh')->all(), 'is_int')));
        $nama = $idPengguna === [] ? [] : $this->anggota->AmbilNamaPengguna($this->konteks->Wajib(), $idPengguna);

        return array_values($riwayat->map(fn (RiwayatStatusDokumen $r): array => [
            'StatusKe' => (string) $r->StatusKe,
            'Oleh' => $r->DiubahOleh === null ? null : ($nama[$r->DiubahOleh] ?? null),
            'Pada' => $r->DiubahPada?->toIso8601String() ?? '',
            'Alasan' => $r->Alasan,
        ])->all());
    }
}
