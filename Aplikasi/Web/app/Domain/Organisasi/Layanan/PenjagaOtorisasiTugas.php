<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Layanan;

use App\Domain\Organisasi\Enum\IzinTenant;
use App\Domain\Organisasi\Kueri\AksesPengguna;
use App\Domain\Tenant\Kueri\RingkasanLanggananTenant;

/**
 * Audit F-03: tugas antrean yang mengubah data (impor, posting) memeriksa ulang otorisasi di setiap potongan, bukan
 * hanya saat dikirim: langganan tenant tidak ditangguhkan, pengguna pemulai masih anggota aktif, dan masih punya izin
 * yang dibutuhkan. Akses dibaca segar (tanpa tembolok) karena pekerja antrean berumur panjang.
 */
final class PenjagaOtorisasiTugas
{
    public function __construct(private readonly RingkasanLanggananTenant $langganan) {}

    /** Null = boleh lanjut; selain itu alasan penghentian yang aman ditampilkan. */
    public function AmbilAlasanDitolak(int $idTenant, int $idPengguna, IzinTenant $izin): ?string
    {
        if ($this->langganan->CekDitangguhkan($idTenant)) {
            return 'langganan usaha sedang ditangguhkan';
        }

        $akses = app()->build(AksesPengguna::class);

        if ($akses->Ambil($idTenant, $idPengguna) === null) {
            return 'pengguna yang memulai sudah tidak aktif di usaha ini';
        }

        if (! $akses->CekIzin($idTenant, $idPengguna, $izin)) {
            return 'pengguna yang memulai tidak lagi punya izin "'.$izin->AmbilLabel().'"';
        }

        return null;
    }
}
