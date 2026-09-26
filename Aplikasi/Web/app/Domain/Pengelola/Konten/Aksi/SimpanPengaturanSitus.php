<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Konten\Aksi;

use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Situs\Model\PengaturanSitus;
use Illuminate\Support\Facades\DB;

/**
 * D-21: menyimpan pengaturan situs pemasaran (identitas, SEO, kontak, media sosial, pengumuman, menu, kaki, tautan
 * unduh). Isian sudah divalidasi `SimpanPengaturanSitusPermintaan`. Langsung berlaku di situs publik.
 */
final class SimpanPengaturanSitus
{
    public function __construct(private readonly PencatatAuditPengelola $audit) {}

    /**
     * @param  array<string, mixed>  $nilai
     */
    public function Jalankan(PenggunaPengelola $pelaku, array $nilai): PengaturanSitus
    {
        return DB::transaction(function () use ($pelaku, $nilai): PengaturanSitus {
            $pengaturan = PengaturanSitus::query()->where('Kunci', PengaturanSitus::KUNCI_UMUM)->lockForUpdate()->first()
                ?? new PengaturanSitus(['Kunci' => PengaturanSitus::KUNCI_UMUM]);
            $lama = $pengaturan->exists ? $pengaturan->Nilai : null;
            $pengaturan->fill(['Nilai' => $nilai, 'IdPenggunaPengelolaPengubah' => $pelaku->Id])->save();

            $this->audit->Catat('situs.pengaturan.ubah', $pengaturan, nilaiLama: $lama, nilaiBaru: $nilai);

            return $pengaturan;
        });
    }
}
