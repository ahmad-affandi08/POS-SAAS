<?php

declare(strict_types=1);

namespace App\Domain\Penjualan\Kueri;

use App\Domain\Organisasi\Kueri\AnggotaOutlet;
use App\Domain\Organisasi\Kueri\MejaOutlet;
use App\Domain\Pemenuhan\Kueri\TiketDapurOutlet;
use App\Domain\Penjualan\Enum\StatusBarisPesanan;
use App\Domain\Penjualan\Enum\StatusPesananTerbuka;
use App\Domain\Penjualan\Model\PesananTerbuka;
use App\Domain\Penjualan\Model\PesananTerbukaDetail;
use Carbon\CarbonImmutable;

/**
 * Snapshot pesanan terbuka satu outlet untuk perangkat kasir/pelayan (F-07 mode meja fase 1, `GET
 * /api/pos/v1/pesanan-terbuka`): semua pesanan `Terbuka` lengkap dengan baris & status tiket dapurnya, ditambah Uuid
 * pesanan yang ditutup (dibayar/dibatalkan) dalam 12 jam terakhir agar perangkat lain menghapusnya dari layar.
 * `Tanda` = hash isi untuk ETag (perangkat menarik tiap 5–10 detik; 304 bila tidak berubah).
 */
final class PesananTerbukaOutlet
{
    private const JAM_DITUTUP = 12;

    public function __construct(
        private readonly MejaOutlet $meja,
        private readonly TiketDapurOutlet $tiket,
        private readonly AnggotaOutlet $anggota,
    ) {}

    /**
     * @return array{Pesanan: list<array<string, mixed>>, Ditutup: list<array{Uuid: string, Status: string}>, Tanda: string}
     */
    public function Ambil(int $idOutlet): array
    {
        $terbuka = PesananTerbuka::query()->where('IdOutlet', $idOutlet)->where('Status', StatusPesananTerbuka::Terbuka->value)->orderBy('DibukaPada')->get();
        $ditutup = PesananTerbuka::query()->where('IdOutlet', $idOutlet)->where('Status', '!=', StatusPesananTerbuka::Terbuka->value)
            ->where('DiubahPada', '>=', CarbonImmutable::now()->subHours(self::JAM_DITUTUP))->orderBy('Id')->get(['Uuid', 'Status']);
        $baris = PesananTerbukaDetail::query()->whereIn('IdPesananTerbuka', $terbuka->pluck('Id')->all())->orderBy('Id')->get()->groupBy('IdPesananTerbuka');
        $meja = $this->meja->AmbilPerId(array_values(array_unique(array_filter($terbuka->pluck('IdMeja')->all(), fn ($id): bool => is_int($id)))));
        $statusTiket = $this->tiket->AmbilStatusPerBaris(array_values($baris->flatten(1)->pluck('Uuid')->all()));
        $idPengguna = array_values(array_unique([...$terbuka->pluck('IdPengguna')->all(), ...$baris->flatten(1)->pluck('IdPengguna')->all()]));
        $namaPengguna = $this->anggota->AmbilNama(array_map(fn ($id): int => (int) $id, $idPengguna));

        $pesanan = array_values($terbuka->map(fn (PesananTerbuka $p): array => [
            'Uuid' => $p->Uuid,
            'Nomor' => $p->Nomor,
            'UuidMeja' => $p->IdMeja === null ? null : ($meja[$p->IdMeja]['Uuid'] ?? null),
            'NamaMeja' => $p->IdMeja === null ? null : ($meja[$p->IdMeja]['Nama'] ?? null),
            'Label' => $p->Label,
            'JumlahTamu' => $p->JumlahTamu,
            'DibukaOleh' => $namaPengguna[$p->IdPengguna]['Nama'] ?? null,
            'DibukaPada' => $p->DibukaPada->toIso8601ZuluString(),
            'HeaderDiubahPada' => $p->HeaderDiubahPada->toIso8601ZuluString(),
            'DikunciBayar' => $p->KunciBayarSampai !== null && $p->KunciBayarSampai->isFuture(),
            'Baris' => array_values(collect($baris->get($p->Id, []))->map(fn (PesananTerbukaDetail $b): array => [
                'Uuid' => $b->Uuid,
                'UuidProduk' => $b->UuidProduk,
                'UuidProdukSatuan' => $b->UuidProdukSatuan,
                'NamaProduk' => $b->NamaProduk,
                'Jumlah' => (string) $b->Jumlah,
                'HargaSatuan' => (string) $b->HargaSatuan,
                'HargaPilihan' => (string) $b->HargaPilihan,
                'Pilihan' => $b->Pilihan ?? [],
                'Catatan' => $b->Catatan,
                'Ronde' => $b->Ronde,
                'Dibatalkan' => $b->Status === StatusBarisPesanan::Dibatalkan,
                'DikirimKeDapur' => $b->DikirimKeDapurPada !== null,
                'StatusDapur' => $statusTiket[$b->Uuid] ?? null,
            ])->all()),
        ])->all());

        $isi = [
            'Pesanan' => $pesanan,
            'Ditutup' => array_values($ditutup->map(fn (PesananTerbuka $p): array => ['Uuid' => $p->Uuid, 'Status' => $p->Status->value])->all()),
        ];

        return $isi + ['Tanda' => hash('sha256', (string) json_encode($isi))];
    }
}
