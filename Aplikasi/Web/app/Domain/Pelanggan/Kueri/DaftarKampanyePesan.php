<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Bersama\Tabel\Data\DataPermintaanTabel;
use App\Domain\Bersama\Tabel\Layanan\PenerapKueriTabel;
use App\Domain\Pelanggan\Enum\KanalKampanye;
use App\Domain\Pelanggan\Enum\StatusKampanye;
use App\Domain\Pelanggan\Enum\StatusPenerimaKampanye;
use App\Domain\Pelanggan\Model\KampanyePesan;
use App\Domain\Pelanggan\Model\Pelanggan;
use App\Domain\Pelanggan\Model\PenerimaKampanye;
use Illuminate\Support\Collection;

/** Daftar & rincian kampanye pesan CRM-07 untuk back-office. Tujuan penerima tidak pernah dikirim ke peramban. */
final class DaftarKampanyePesan
{
    public const KOLOM_URUT = ['Nama', 'DibuatPada', 'JumlahPenerima'];

    public const KOLOM_SARING = ['Status', 'Kanal'];

    public const URUT_BAWAAN = '-DibuatPada';

    public const KOLOM_URUT_PENERIMA = ['TerkirimPada'];

    public const KOLOM_SARING_PENERIMA = ['Status'];

    /**
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilTabel(DataPermintaanTabel $permintaan): array
    {
        $status = $permintaan->AmbilDaftar('Status', array_map(fn (StatusKampanye $s): string => $s->value, StatusKampanye::cases()));
        $kanal = $permintaan->AmbilDaftar('Kanal', array_map(fn (KanalKampanye $k): string => $k->value, KanalKampanye::cases()));
        $kueri = KampanyePesan::query()
            ->when($status !== [], fn ($k) => $k->whereIn('Status', $status))
            ->when($kanal !== [], fn ($k) => $k->whereIn('Kanal', $kanal))
            ->when($permintaan->cari !== '', fn ($k) => $k->where('Nama', 'like', PenerapKueriTabel::PolaCari($permintaan->cari)));

        return PenerapKueriTabel::Terapkan(
            $kueri,
            $permintaan,
            ['Nama' => 'Nama', 'DibuatPada' => 'DibuatPada', 'JumlahPenerima' => 'JumlahPenerima'],
            fn (Collection $baris): array => array_values($baris->map(fn (KampanyePesan $k): array => self::Ringkas($k))->all()),
        );
    }

    /**
     * @return array{Uuid: string, Nama: string, Kanal: string, LabelKanal: string, Status: string, LabelStatus: string, DijadwalkanPada: string|null, MulaiPada: string|null, SelesaiPada: string|null, JumlahPenerima: int, JumlahTerkirim: int, JumlahGagal: int, JumlahDilewati: int, DibuatPada: string|null}
     */
    public static function Ringkas(KampanyePesan $k): array
    {
        return [
            'Uuid' => $k->Uuid,
            'Nama' => $k->Nama,
            'Kanal' => $k->Kanal->value,
            'LabelKanal' => $k->Kanal->AmbilLabel(),
            'Status' => $k->Status->value,
            'LabelStatus' => $k->Status->AmbilLabel(),
            'DijadwalkanPada' => $k->DijadwalkanPada?->toIso8601ZuluString(),
            'MulaiPada' => $k->MulaiPada?->toIso8601ZuluString(),
            'SelesaiPada' => $k->SelesaiPada?->toIso8601ZuluString(),
            'JumlahPenerima' => $k->JumlahPenerima,
            'JumlahTerkirim' => $k->JumlahTerkirim,
            'JumlahGagal' => $k->JumlahGagal,
            'JumlahDilewati' => $k->JumlahDilewati,
            'DibuatPada' => $k->DibuatPada?->toIso8601ZuluString(),
        ];
    }

    /**
     * Penerima kampanye (nama pelanggan, status, galat), tanpa nomor/email.
     *
     * @return array{Data: list<array<string, mixed>>, Meta: array{Halaman: int, PerHalaman: int, Total: int, JumlahHalaman: int}}
     */
    public function AmbilPenerima(KampanyePesan $kampanye, DataPermintaanTabel $permintaan): array
    {
        $status = $permintaan->AmbilDaftar('Status', array_map(fn (StatusPenerimaKampanye $s): string => $s->value, StatusPenerimaKampanye::cases()));
        $kueri = PenerimaKampanye::query()
            ->where('IdKampanyePesan', $kampanye->Id)
            ->when($status !== [], fn ($k) => $k->whereIn('Status', $status));

        return PenerapKueriTabel::Terapkan($kueri, $permintaan, ['TerkirimPada' => 'TerkirimPada'], function (Collection $baris): array {
            /** @var Collection<int, PenerimaKampanye> $baris */
            $pelanggan = Pelanggan::query()
                ->whereIn('Id', $baris->map(fn (PenerimaKampanye $p): int => $p->IdPelanggan)->unique()->values()->all())
                ->get(['Id', 'Uuid', 'Nama'])
                ->keyBy('Id');

            return array_values($baris->map(function (PenerimaKampanye $p) use ($pelanggan): array {
                /** @var Pelanggan|null $orang */
                $orang = $pelanggan->get($p->IdPelanggan);

                return [
                    'Kunci' => (string) $p->Id,
                    'UuidPelanggan' => $orang?->Uuid,
                    'NamaPelanggan' => $orang->Nama ?? '—',
                    'Status' => $p->Status->value,
                    'LabelStatus' => $p->Status->AmbilLabel(),
                    'PesanGalat' => $p->PesanGalat,
                    'TerkirimPada' => $p->TerkirimPada?->toIso8601ZuluString(),
                ];
            })->all());
        });
    }
}
