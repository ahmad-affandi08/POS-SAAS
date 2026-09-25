<?php

declare(strict_types=1);

namespace App\Domain\Pemenuhan\Aksi;

use App\Domain\Katalog\Kueri\StasiunDapurProduk;
use App\Domain\Organisasi\Kueri\DaftarStasiunDapur;
use App\Domain\Pemenuhan\Data\DataKirimDapur;
use App\Domain\Pemenuhan\Enum\StatusBarisTiket;
use App\Domain\Pemenuhan\Enum\StatusTiketDapur;
use App\Domain\Pemenuhan\Model\TiketDapur;
use App\Domain\Pemenuhan\Model\TiketDapurDetail;
use Illuminate\Support\Facades\DB;

/**
 * F-10b fase 1: membuat tiket dapur satu ronde dokumen, satu tiket per stasiun. Baris dirutekan menurut stasiun
 * kategori produknya; kategori tanpa stasiun atau stasiunnya diarsipkan → stasiun bawaan. Tenant tanpa stasiun aktif:
 * tidak ada tiket. Idempoten per baris: baris yang sudah punya tiket (UuidBaris) dilewati.
 */
final class KirimKeDapur
{
    public function __construct(
        private readonly StasiunDapurProduk $stasiunProduk,
        private readonly DaftarStasiunDapur $daftarStasiun,
    ) {}

    /**
     * @return list<TiketDapur>
     */
    public function Jalankan(DataKirimDapur $data): array
    {
        if ($data->baris === []) {
            return [];
        }

        $perutean = $this->daftarStasiun->AmbilPerutean();

        if ($perutean['Bawaan'] === null) {
            return [];
        }

        return DB::transaction(function () use ($data, $perutean): array {
            $sudah = TiketDapurDetail::query()->whereIn('UuidBaris', array_map(fn ($b): string => $b->uuidBaris, $data->baris))->pluck('UuidBaris')->all();
            $baris = array_values(array_filter($data->baris, fn ($b): bool => ! in_array($b->uuidBaris, $sudah, true)));

            if ($baris === []) {
                return [];
            }

            $stasiunProduk = $this->stasiunProduk->AmbilPerProduk(array_values(array_unique(array_map(fn ($b): int => $b->idProduk, $baris))));
            $perStasiun = [];

            foreach ($baris as $b) {
                $idStasiun = $stasiunProduk[$b->idProduk] ?? null;
                $idStasiun = $idStasiun !== null && isset($perutean['Aktif'][$idStasiun]) ? $idStasiun : $perutean['Bawaan'];
                $perStasiun[$idStasiun][] = $b;
            }

            $tiket = [];

            foreach ($perStasiun as $idStasiun => $isi) {
                $satu = TiketDapur::query()->create([
                    'IdOutlet' => $data->idOutlet,
                    'IdStasiunDapur' => $idStasiun,
                    'IdPesananTerbuka' => $data->idPesananTerbuka,
                    'IdPenjualan' => $data->idPenjualan,
                    'NomorDokumen' => $data->nomorDokumen,
                    'NamaMeja' => $data->namaMeja,
                    'Label' => $data->label,
                    'Ronde' => $data->ronde,
                    'Status' => StatusTiketDapur::Antre,
                    'DikirimPada' => $data->dikirimPada,
                ]);

                foreach ($isi as $b) {
                    TiketDapurDetail::query()->create([
                        'IdTiketDapur' => $satu->Id,
                        'UuidBaris' => $b->uuidBaris,
                        'NamaProduk' => $b->namaProduk,
                        'Jumlah' => $b->jumlah,
                        'Pilihan' => $b->pilihan === [] ? null : $b->pilihan,
                        'Catatan' => $b->catatan,
                        'Status' => StatusBarisTiket::Aktif,
                    ]);
                }

                $tiket[] = $satu;
            }

            return $tiket;
        });
    }
}
