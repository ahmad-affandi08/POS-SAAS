<?php

declare(strict_types=1);

namespace App\Domain\Promo\Kueri;

use App\Domain\Penjualan\Enum\JenisAksiPromo;
use App\Domain\Promo\Model\Promo;

/**
 * Promo tenant untuk back-office (F-16c): 200 promo terbaru (aktif lebih dulu) dengan ringkasan pemakaian, dipakai
 * `TabelData` mode lokal. `Definisi` ikut dikirim untuk formulir ubah.
 */
final class DaftarPromo
{
    public const BATAS = 200;

    public function __construct(private readonly PemakaianPromo $pemakaian) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function AmbilSemua(): array
    {
        $promo = Promo::query()->orderBy('Status')->orderByDesc('Id')->limit(self::BATAS)->get();
        $ringkasan = $this->pemakaian->AmbilRingkasan(array_values($promo->pluck('Id')->all()));

        return array_values($promo->map(fn (Promo $p): array => [
            ...self::Petakan($p),
            'JumlahPakai' => $ringkasan[$p->Id]['JumlahPakai'] ?? 0,
            'TotalDiskon' => $ringkasan[$p->Id]['TotalDiskon'] ?? '0.00',
        ])->all());
    }

    /**
     * @return array<string, mixed>
     */
    public static function Petakan(Promo $p): array
    {
        /** @var array{Jenis?: string} $aksi */
        $aksi = is_array($p->Definisi['Aksi'] ?? null) ? $p->Definisi['Aksi'] : [];
        $jenis = JenisAksiPromo::tryFrom((string) ($aksi['Jenis'] ?? ''));

        return [
            'Uuid' => $p->Uuid,
            'Kode' => $p->Kode,
            'Nama' => $p->Nama,
            'JenisAksi' => $jenis?->value,
            'LabelAksi' => $jenis?->AmbilLabel() ?? '-',
            'Prioritas' => $p->Prioritas,
            'Eksklusif' => $p->Eksklusif,
            'MulaiPada' => $p->MulaiPada?->toIso8601ZuluString(),
            'SelesaiPada' => $p->SelesaiPada?->toIso8601ZuluString(),
            'Kuota' => $p->Kuota,
            'KuotaTerpakai' => $p->KuotaTerpakai,
            'Status' => $p->Status->value,
            'Definisi' => $p->Definisi,
        ];
    }
}
