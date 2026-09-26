<?php

declare(strict_types=1);

namespace App\Domain\Promo\Kueri;

use App\Domain\Akuntansi\Enum\JenisSumberJurnal;
use App\Domain\Akuntansi\Kueri\DaftarAkunPilihan;
use App\Domain\Akuntansi\Kueri\JurnalSumber;
use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Pembelian\Kueri\DaftarPemasok;
use App\Domain\Promo\Enum\StatusKlaimPromo;
use App\Domain\Promo\Model\KlaimPromoPemasok;
use App\Domain\Promo\Model\PenerimaanKlaimPemasok;
use App\Domain\Promo\Model\Promo;

/**
 * Klaim promo ke pemasok (F-16c bagian 4b): ringkasan klaim terbuka per pemasok (jumlah transaksi, total, tanggal
 * tertua, promo, sisa hutang ke pemasok untuk potong hutang bagian 4e), dan riwayat penerimaan klaim (100 terbaru)
 * dengan cara penyelesaian & tautan jurnal.
 */
final class DaftarKlaimPemasok
{
    public function __construct(
        private readonly DaftarPemasok $pemasok,
        private readonly DaftarAkunPilihan $akun,
        private readonly JurnalSumber $jurnal,
    ) {}

    /**
     * @return list<array{UuidPemasok: string, NamaPemasok: string, JumlahTransaksi: int, Total: string, TanggalTertua: string, SisaHutang: string, Promo: string}>
     */
    public function AmbilTerbuka(): array
    {
        $klaim = KlaimPromoPemasok::query()->where('Status', StatusKlaimPromo::Terbuka->value)->orderBy('TanggalBisnis')->get();
        $pemasok = $this->pemasok->AmbilRingkas(array_values(array_unique(array_map('intval', $klaim->pluck('IdPemasok')->all()))));
        $kodePromo = Promo::query()->whereKey($klaim->pluck('IdPromo')->unique()->all())->pluck('Kode', 'Id');
        $sisaHutang = $this->pemasok->AmbilSisaHutang(array_keys($pemasok));
        $hasil = [];

        foreach ($klaim->groupBy('IdPemasok') as $idPemasok => $daftar) {
            $hasil[] = [
                'UuidPemasok' => $pemasok[(int) $idPemasok]['Uuid'] ?? '',
                'NamaPemasok' => $pemasok[(int) $idPemasok]['Nama'] ?? '',
                'JumlahTransaksi' => $daftar->count(),
                'Total' => $daftar->reduce(fn (Uang $t, KlaimPromoPemasok $k): Uang => $t->Tambah(Uang::Dari((string) $k->Jumlah)), Uang::Nol())->KeString(),
                'TanggalTertua' => $daftar->first()?->TanggalBisnis->toDateString() ?? '',
                'SisaHutang' => $sisaHutang[(int) $idPemasok] ?? '0.00',
                'Promo' => implode(', ', array_values(array_unique(array_map(fn (int $id): string => (string) ($kodePromo[$id] ?? ''), array_map('intval', $daftar->pluck('IdPromo')->all()))))),
            ];
        }

        usort($hasil, fn (array $a, array $b): int => strcmp($a['NamaPemasok'], $b['NamaPemasok']));

        return $hasil;
    }

    /**
     * @return list<array{Uuid: string, Tanggal: string, NamaPemasok: string, Jumlah: string, JumlahKlaim: int, Cara: string, AkunKasBank: string|null, Keterangan: string|null, UuidJurnal: string|null, NomorJurnal: string|null}>
     */
    public function AmbilPenerimaan(): array
    {
        $penerimaan = PenerimaanKlaimPemasok::query()->orderByDesc('Tanggal')->orderByDesc('Id')->limit(100)->get();
        $pemasok = $this->pemasok->AmbilRingkas(array_values(array_unique(array_map('intval', $penerimaan->pluck('IdPemasok')->all()))));
        $akun = $this->akun->AmbilBanyak(array_values(array_unique(array_map('intval', $penerimaan->pluck('IdAkunKasBank')->filter()->all()))));
        $jumlahKlaim = KlaimPromoPemasok::query()->whereIn('IdPenerimaanKlaimPemasok', $penerimaan->pluck('Id')->all())
            ->groupBy('IdPenerimaanKlaimPemasok')->selectRaw('IdPenerimaanKlaimPemasok, COUNT(*) AS Jumlah')->pluck('Jumlah', 'IdPenerimaanKlaimPemasok');

        return array_values($penerimaan->map(function (PenerimaanKlaimPemasok $p) use ($pemasok, $akun, $jumlahKlaim): array {
            $jurnal = $p->IdPembayaranHutang !== null
                ? ($this->jurnal->Ambil(JenisSumberJurnal::PembayaranHutang, $p->IdPembayaranHutang)[0] ?? null)
                : ($this->jurnal->Ambil(JenisSumberJurnal::PenerimaanKlaimPemasok, $p->Id)[0] ?? null);

            return [
                'Uuid' => $p->Uuid,
                'Tanggal' => $p->Tanggal->toDateString(),
                'NamaPemasok' => $pemasok[$p->IdPemasok]['Nama'] ?? '',
                'Jumlah' => (string) $p->Jumlah,
                'JumlahKlaim' => (int) ($jumlahKlaim[$p->Id] ?? 0),
                'Cara' => $p->Cara->AmbilLabel(),
                'AkunKasBank' => $p->IdAkunKasBank !== null && isset($akun[$p->IdAkunKasBank]) ? $akun[$p->IdAkunKasBank]['Kode'].' '.$akun[$p->IdAkunKasBank]['Nama'] : null,
                'Keterangan' => $p->Keterangan,
                'UuidJurnal' => $jurnal['Uuid'] ?? null,
                'NomorJurnal' => $jurnal['Nomor'] ?? null,
            ];
        })->all());
    }
}
