<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Aksi;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Organisasi\Model\StasiunDapur;
use App\Domain\Tenant\Layanan\PenguncianTenant;
use Illuminate\Support\Facades\DB;

/**
 * F-01/F-10a (BR-01.1): stasiun dapur template sektor menjadi stasiun tenant. Aditif & idempoten: nama yang sudah
 * ada (tanpa beda huruf besar/kecil, termasuk yang diarsipkan) dilewati; batas stasiun aktif tetap berlaku.
 */
final class TambahkanStasiunDapurTemplate
{
    public function __construct(
        private readonly KonteksTenant $konteks,
        private readonly PenguncianTenant $penguncian,
        private readonly PencatatAudit $audit,
    ) {}

    /**
     * @param  list<string>  $nama
     * @return list<string> nama stasiun yang ditambahkan
     */
    public function Jalankan(array $nama): array
    {
        return DB::transaction(function () use ($nama): array {
            $this->penguncian->Kunci($this->konteks->Wajib());
            $semua = StasiunDapur::query()->get(['Nama', 'Urutan', 'Status']);
            $ada = array_map(fn (mixed $satu): string => mb_strtolower(trim((string) $satu)), $semua->pluck('Nama')->all());
            $aktif = $semua->filter(fn (StasiunDapur $s): bool => $s->Status->value === 'Aktif')->count();
            $urutan = (int) $semua->max('Urutan');
            $ditambahkan = [];

            foreach ($nama as $satu) {
                $bersih = mb_substr(trim($satu), 0, 60);

                if ($bersih === '' || in_array(mb_strtolower($bersih), $ada, true) || $aktif >= SimpanStasiunDapur::MAKS_AKTIF) {
                    continue;
                }

                StasiunDapur::query()->create(['Nama' => $bersih, 'Urutan' => ++$urutan]);
                $ada[] = mb_strtolower($bersih);
                $aktif++;
                $ditambahkan[] = $bersih;
            }

            if ($ditambahkan !== []) {
                $this->audit->Catat('stasiun-dapur.tambah-template', nilaiBaru: ['Nama' => $ditambahkan]);
            }

            return $ditambahkan;
        });
    }
}
