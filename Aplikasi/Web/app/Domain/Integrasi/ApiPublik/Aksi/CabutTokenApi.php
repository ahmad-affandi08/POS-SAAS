<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\ApiPublik\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Integrasi\ApiPublik\Model\TokenApiTenant;
use Illuminate\Support\Facades\DB;

/** X7 bagian 1: cabut token API publik. Berlaku seketika; mencabut ulang tidak mengubah apa pun. */
final class CabutTokenApi
{
    public function __construct(private readonly PencatatAudit $audit) {}

    public function Jalankan(TokenApiTenant $token, int $idPengguna): void
    {
        DB::transaction(function () use ($token, $idPengguna): void {
            $baris = TokenApiTenant::query()->whereKey($token->Id)->lockForUpdate()->firstOrFail();

            if ($baris->DicabutPada !== null) {
                return;
            }

            $baris->fill(['DicabutPada' => now(), 'DicabutOleh' => $idPengguna])->save();
            $this->audit->Catat('integrasi.token-api.cabut', $baris, ['Dicabut' => false], ['Dicabut' => true]);
        });
    }
}
