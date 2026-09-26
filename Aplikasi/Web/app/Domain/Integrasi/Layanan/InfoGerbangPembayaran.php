<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Layanan;

use App\Domain\Integrasi\Model\GerbangPembayaranTenant;

/**
 * Ringkasan gerbang pembayaran milik tenant aktif untuk back-office (F-08 QRIS dinamis, v2.06): ada/tidaknya gerbang
 * aktif yang bisa dipakai, label penyedianya, dan tautan ke halaman pengaturannya. Kredensial & pengaturan tidak
 * pernah keluar dari sini.
 */
final class InfoGerbangPembayaran
{
    public const TAUTAN = '/kelola/pembayaran/gerbang';

    public function __construct(private readonly KatalogPenyediaGerbang $katalog) {}

    /**
     * @return array{Aktif: bool, Penyedia: string|null, Tautan: string}
     */
    public function Ambil(): array
    {
        $baris = GerbangPembayaranTenant::query()->where('Aktif', true)->first(['Id', 'Penyedia', 'Aktif']);
        $aktif = $baris !== null && $this->katalog->CekDiizinkan($baris->Penyedia);

        return ['Aktif' => $aktif, 'Penyedia' => $aktif ? $baris->Penyedia->AmbilLabel() : null, 'Tautan' => self::TAUTAN];
    }
}
