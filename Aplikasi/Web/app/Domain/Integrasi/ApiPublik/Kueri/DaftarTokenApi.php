<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Kueri;

use App\Domain\Integrasi\ApiPublik\Enum\CakupanApi;
use App\Domain\Integrasi\ApiPublik\Model\TokenApiTenant;

/** Daftar token API publik tenant untuk halaman Pengaturan › API (tanpa hash; hanya prefiks). */
final class DaftarTokenApi
{
    /**
     * @return list<array{Uuid: string, Nama: string, Prefiks: string, Cakupan: list<string>, Aktif: bool, DibuatPada: string|null, TerakhirDipakaiPada: string|null, KedaluwarsaPada: string|null, DicabutPada: string|null}>
     */
    public function Ambil(): array
    {
        return array_values(TokenApiTenant::query()->orderByRaw('`DicabutPada` IS NULL DESC')->orderByDesc('Id')->limit(50)->get()
            ->map(fn (TokenApiTenant $t): array => [
                'Uuid' => $t->Uuid,
                'Nama' => $t->Nama,
                'Prefiks' => $t->Prefiks,
                'Cakupan' => $t->Cakupan,
                'Aktif' => $t->CekBerlaku(),
                'DibuatPada' => $t->DibuatPada?->toIso8601ZuluString(),
                'TerakhirDipakaiPada' => $t->TerakhirDipakaiPada?->toIso8601ZuluString(),
                'KedaluwarsaPada' => $t->KedaluwarsaPada?->toIso8601ZuluString(),
                'DicabutPada' => $t->DicabutPada?->toIso8601ZuluString(),
            ])->all());
    }

    /** @return list<array{Nilai: string, Label: string}> */
    public static function AmbilOpsiCakupan(): array
    {
        return array_map(fn (CakupanApi $c): array => ['Nilai' => $c->value, 'Label' => $c->AmbilLabel()], CakupanApi::cases());
    }
}
