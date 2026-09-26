<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Aksi;

use App\Domain\Bersama\Dokumen\Enum\JenisDokumenBernomor;
use App\Domain\Bersama\Dokumen\Layanan\PenomorDokumen;
use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Domain\Penjualan\Data\DataBarisPesanSendiri;
use App\Domain\Penjualan\Data\DataKonteksPesanSendiri;
use App\Domain\Penjualan\Data\DataPesanSendiri;
use App\Domain\Penjualan\Enum\StatusPesananSendiri;
use App\Domain\Penjualan\Layanan\PenandaKedaluwarsaPesananSendiri;
use App\Domain\Penjualan\Layanan\PenghitungPesanSendiri;
use App\Domain\Penjualan\Model\PesananSendiri;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * F-17 Self-Order QR Meja: tamu mengirim pesanan dari halaman publik meja. Idempoten per `Uuid` peramban (kiriman ulang
 * mengembalikan pesanan yang sama; Uuid yang sama di meja lain → `UuidDipakai`). Harga dihitung ulang server
 * (`PenghitungPesanSendiri`). Batas 5 pesanan `MenungguKonfirmasi` per meja (`TerlaluBanyakPesanan`). Nomor
 * `QR/{KodeOutlet}/{YYMMDD}-{SEQ4}` urut per outlet per tanggal lokal outlet. Tidak menyentuh stok/jurnal.
 */
final class BuatPesananSendiri
{
    public const BATAS_TERTUNDA_PER_MEJA = 5;

    public function __construct(
        private readonly PenghitungPesanSendiri $penghitung,
        private readonly PenandaKedaluwarsaPesananSendiri $kedaluwarsa,
        private readonly PenomorDokumen $penomor,
    ) {}

    /**
     * @return array{0: PesananSendiri, 1: bool} [pesanan, baru dibuat]
     */
    public function Jalankan(DataKonteksPesanSendiri $konteks, DataPesanSendiri $data, ?string $hashIp): array
    {
        $lama = $this->CariLama($konteks, $data->uuid);

        if ($lama !== null) {
            return [$lama, false];
        }

        $baris = array_map(fn (DataBarisPesanSendiri $b): array => ['UuidProduk' => $b->uuidProduk, 'Jumlah' => $b->jumlah, 'Pilihan' => $b->pilihan], $data->baris);
        $hitung = $this->penghitung->Hitung($konteks->idOutlet, $baris);
        $this->kedaluwarsa->TandaiMeja($konteks->idMeja);

        try {
            return DB::transaction(function () use ($konteks, $data, $hitung, $hashIp): array {
                $tertunda = PesananSendiri::query()
                    ->where('IdMeja', $konteks->idMeja)
                    ->where('Status', StatusPesananSendiri::MenungguKonfirmasi->value)
                    ->where('DibuatPada', '>=', PenandaKedaluwarsaPesananSendiri::AmbilBatas())
                    ->count();

                if ($tertunda >= self::BATAS_TERTUNDA_PER_MEJA) {
                    throw new PelanggaranAturanBisnis('TerlaluBanyakPesanan', 'Masih ada '.self::BATAS_TERTUNDA_PER_MEJA.' pesanan meja ini yang menunggu konfirmasi staf. Tunggu sebentar atau panggil staf.', 'Uuid', 429);
                }

                $tanggal = CarbonImmutable::now()->setTimezone($konteks->zonaWaktu);
                $urut = $this->penomor->AmbilBerikutnyaHarian(JenisDokumenBernomor::PesananSendiri, $tanggal->format('Y-m-d'), $konteks->idOutlet);
                $nomor = sprintf('%s/%s/%s-%s', JenisDokumenBernomor::PesananSendiri->AmbilAwalan(), $konteks->kodeOutlet, $tanggal->format('ymd'), str_pad((string) $urut, 4, '0', STR_PAD_LEFT));

                $pesanan = PesananSendiri::query()->create([
                    'Uuid' => $data->uuid,
                    'IdOutlet' => $konteks->idOutlet,
                    'IdMeja' => $konteks->idMeja,
                    'Nomor' => $nomor,
                    'NamaPemesan' => $data->namaPemesan,
                    'Catatan' => $data->catatan,
                    'Baris' => array_values(array_map(fn (DataBarisPesanSendiri $b, array $h): array => [
                        'Uuid' => $b->uuid,
                        'UuidProduk' => $h['UuidProduk'],
                        'UuidProdukSatuan' => $h['UuidProdukSatuan'],
                        'NamaProduk' => $h['NamaProduk'],
                        'Jumlah' => $h['Jumlah']->KeString(),
                        'HargaSatuan' => $h['HargaSatuan']->KeString(),
                        'HargaPilihan' => $h['HargaPilihan']->KeString(),
                        'Pilihan' => $h['Pilihan'],
                        'Catatan' => $b->catatan,
                    ], $data->baris, $hitung['Baris'])),
                    'Subtotal' => $hitung['Subtotal']->KeString(),
                    'Status' => StatusPesananSendiri::MenungguKonfirmasi,
                    'HashIp' => $hashIp,
                ]);

                return [$pesanan, true];
            });
        } catch (QueryException $galat) {
            // Kiriman ganda bersamaan dengan Uuid yang sama: yang kalah membaca pesanan pemenang.
            $lama = str_contains($galat->getMessage(), 'UniqPesananSendiriIdTenantUuid') ? $this->CariLama($konteks, $data->uuid) : null;

            if ($lama === null) {
                throw $galat;
            }

            return [$lama, false];
        }
    }

    private function CariLama(DataKonteksPesanSendiri $konteks, string $uuid): ?PesananSendiri
    {
        $lama = PesananSendiri::query()->where('Uuid', $uuid)->first();

        if ($lama !== null && $lama->IdMeja !== $konteks->idMeja) {
            throw new PelanggaranAturanBisnis('UuidDipakai', 'Kode pesanan ini sudah dipakai. Muat ulang halaman lalu kirim lagi.', 'Uuid', 409);
        }

        return $lama;
    }
}
