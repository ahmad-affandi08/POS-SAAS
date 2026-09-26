<?php

declare(strict_types=1);

namespace App\Domain\Pelanggan\Kueri;

use App\Domain\Pelanggan\Model\PengaturanPengingatPiutang;

/** Pengaturan pengingat piutang otomatis tenant aktif (D-23 D bagian 4b); tanpa baris = bawaan (mati, H-3, saat lewat). */
final class PengaturanPengingatPiutangTenant
{
    /**
     * @return array{Aktif: bool, HariSebelum: int, IngatkanSaatLewat: bool}
     */
    public function Ambil(): array
    {
        $p = PengaturanPengingatPiutang::query()->first() ?? new PengaturanPengingatPiutang;

        return ['Aktif' => $p->Aktif, 'HariSebelum' => $p->HariSebelum, 'IngatkanSaatLewat' => $p->IngatkanSaatLewat];
    }
}
