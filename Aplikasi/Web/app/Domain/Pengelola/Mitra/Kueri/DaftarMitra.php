<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Mitra\Kueri;

use App\Domain\Bersama\Nilai\Uang;
use App\Domain\Tenant\Enum\StatusKomisiMitra;
use App\Domain\Tenant\Enum\StatusLangganan;
use App\Domain\Tenant\Model\AtribusiMitra;
use App\Domain\Tenant\Model\KomisiMitra;
use App\Domain\Tenant\Model\Langganan;
use App\Domain\Tenant\Model\Mitra;
use App\Domain\Tenant\Model\PencairanKomisi;
use App\Domain\Tenant\Model\Tenant;

/**
 * Tampilan konsol P-12: daftar mitra beserta ringkasan rujukan & komisi, dan rincian satu mitra. Nomor HP, NPWP,
 * dan rekening hanya tampil tersamar (4 digit terakhir). Data tenant yang ditampilkan hanya nama & status langganan
 * (BR-P12.3: mitra tidak otomatis mendapat akses data tenant).
 */
final class DaftarMitra
{
    /** @return list<array<string, mixed>> */
    public function AmbilSemua(): array
    {
        $jumlahTenant = AtribusiMitra::query()->selectRaw('IdMitra, COUNT(*) AS Jumlah')->groupBy('IdMitra')->pluck('Jumlah', 'IdMitra');
        $tertunda = KomisiMitra::query()->where('Status', StatusKomisiMitra::Tertunda->value)
            ->selectRaw('IdMitra, SUM(Jumlah) AS Total')->groupBy('IdMitra')->pluck('Total', 'IdMitra');

        return array_values(Mitra::query()->orderBy('Nama')->get()->map(fn (Mitra $m): array => [
            ...$this->Ringkas($m),
            'JumlahTenant' => (int) ($jumlahTenant[$m->Id] ?? 0),
            'KomisiTertunda' => Uang::Dari((string) ($tertunda[$m->Id] ?? '0'))->KeString(),
        ])->all());
    }

    /** @return array<string, mixed> */
    public function AmbilRincian(Mitra $mitra): array
    {
        $atribusi = AtribusiMitra::query()->where('IdMitra', $mitra->Id)->orderByDesc('MulaiPada')->get();
        $tenant = Tenant::query()->whereKey($atribusi->pluck('IdTenant')->all())->pluck('Nama', 'Id');
        $langganan = Langganan::query()->whereIn('IdTenant', $atribusi->pluck('IdTenant')->all())->get()->keyBy('IdTenant');
        $komisi = KomisiMitra::query()->where('IdMitra', $mitra->Id)->orderByDesc('Id')->limit(200)->get();
        $namaTenant = Tenant::query()->whereKey($komisi->pluck('IdTenant')->unique()->all())->pluck('Nama', 'Id');

        return [
            ...$this->Ringkas($mitra),
            'Email' => $mitra->Email,
            'NamaBank' => $mitra->NamaBank,
            'NamaPemilikRekening' => $mitra->NamaPemilikRekening,
            'Catatan' => $mitra->Catatan,
            'NoHpTersamar' => self::Samarkan($mitra->NoHp),
            'NpwpTersamar' => self::Samarkan($mitra->Npwp),
            'RekeningTersamar' => self::Samarkan($mitra->NomorRekening),
            'TautanPendaftaran' => url('/daftar?mitra='.$mitra->Kode),
            'Tenant' => $atribusi->map(function (AtribusiMitra $a) use ($tenant, $langganan): array {
                $l = $langganan->get($a->IdTenant);

                return [
                    'Nama' => $tenant[$a->IdTenant] ?? '—',
                    'Sumber' => $a->Sumber,
                    'MulaiPada' => $a->MulaiPada->toIso8601String(),
                    'StatusLangganan' => $l instanceof Langganan ? $l->Status->value : null,
                    'Berbayar' => $l instanceof Langganan && $l->Status === StatusLangganan::Aktif,
                ];
            })->all(),
            'Komisi' => $komisi->map(fn (KomisiMitra $k): array => [
                'Uuid' => $k->Uuid,
                'NomorTagihan' => $k->NomorTagihan,
                'NamaTenant' => $namaTenant[$k->IdTenant] ?? '—',
                'DasarKomisi' => $k->DasarKomisi,
                'PersenKomisi' => $k->PersenKomisi,
                'Jumlah' => $k->Jumlah,
                'Status' => $k->Status->value,
                'AlasanBatal' => $k->AlasanBatal,
                'DibuatPada' => $k->DibuatPada?->toIso8601String(),
            ])->all(),
            'Pencairan' => PencairanKomisi::query()->where('IdMitra', $mitra->Id)->orderByDesc('Periode')->get()->map(fn (PencairanKomisi $p): array => [
                'Uuid' => $p->Uuid, 'Periode' => $p->Periode, 'Total' => $p->Total, 'PotonganPajak' => $p->PotonganPajak,
                'JumlahBersih' => $p->JumlahBersih, 'DibayarPada' => $p->DibayarPada->toDateString(), 'Catatan' => $p->Catatan,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function Ringkas(Mitra $m): array
    {
        return [
            'Uuid' => $m->Uuid, 'Kode' => $m->Kode, 'Nama' => $m->Nama, 'Jenis' => $m->Jenis->value, 'LabelJenis' => $m->Jenis->AmbilLabel(),
            'Status' => $m->Status->value, 'PersenKomisi' => $m->PersenKomisi, 'KomisiBerulang' => $m->KomisiBerulang,
        ];
    }

    private static function Samarkan(?string $nilai): ?string
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        $polos = preg_replace('/\D/', '', $nilai) ?? '';

        return '•••• '.substr($polos === '' ? $nilai : $polos, -4);
    }
}
