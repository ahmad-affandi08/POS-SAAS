<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Kueri;

use App\Domain\Integrasi\Whatsapp\PesanWhatsapp;
use App\Domain\Organisasi\Enum\StatusKeanggotaan;
use App\Domain\Organisasi\Model\Pengguna;
use App\Domain\Organisasi\Model\TenantPengguna;

/**
 * Nama & email anggota aktif satu tenant, untuk pemberitahuan sistem (misal ringkasan pagi Kotak Tindakan, D-23 D).
 * Anggota tanpa email (kasir berbasis PIN, D-22) dilewati.
 */
final class KontakAnggotaTenant
{
    /**
     * @return list<array{Id: int, Nama: string, Email: string, Pemilik: bool}>
     */
    public function Ambil(int $idTenant): array
    {
        $anggota = TenantPengguna::query()
            ->where('IdTenant', $idTenant)
            ->where('Status', StatusKeanggotaan::Aktif->value)
            ->pluck('Pemilik', 'IdPengguna')
            ->all();

        if ($anggota === []) {
            return [];
        }

        $hasil = [];

        foreach (Pengguna::query()->whereIn('Id', array_keys($anggota))->whereNotNull('Email')->orderBy('Id')->get() as $pengguna) {
            $hasil[] = [
                'Id' => $pengguna->Id,
                'Nama' => $pengguna->Nama,
                'Email' => (string) $pengguna->Email,
                'Pemilik' => (bool) $anggota[$pengguna->Id],
            ];
        }

        return $hasil;
    }

    /**
     * Nama & nomor WhatsApp anggota aktif satu tenant untuk notifikasi tenant (D-33: notifikasi tenant hanya lewat
     * WhatsApp). Anggota tanpa nomor HP yang sah dilewati.
     *
     * @return list<array{Id: int, Nama: string, NoHp: string, Pemilik: bool}>
     */
    public function AmbilWhatsapp(int $idTenant): array
    {
        $anggota = TenantPengguna::query()
            ->where('IdTenant', $idTenant)
            ->where('Status', StatusKeanggotaan::Aktif->value)
            ->pluck('Pemilik', 'IdPengguna')
            ->all();

        if ($anggota === []) {
            return [];
        }

        $hasil = [];

        foreach (Pengguna::query()->whereIn('Id', array_keys($anggota))->whereNotNull('NoHp')->orderBy('Id')->get() as $pengguna) {
            $nomor = PesanWhatsapp::AmbilNomorSah($pengguna->NoHp);

            if ($nomor !== null) {
                $hasil[] = ['Id' => $pengguna->Id, 'Nama' => $pengguna->Nama, 'NoHp' => $nomor, 'Pemilik' => (bool) $anggota[$pengguna->Id]];
            }
        }

        return $hasil;
    }
}
