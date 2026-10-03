<?php

declare(strict_types=1);

namespace App\Domain\Lisensi\Kontrak;

/**
 * D-35 edisi Lisensi: Owner mengatur email, WhatsApp, dan penyimpanan berkas server tokonya sendiri dari back-office
 * (Pengaturan › Email & WhatsApp server). Konfigurasinya milik Platform Pengelola (P-05), sedangkan kode tenant tidak
 * boleh memakai domain Pengelola (test arsitektur), jadi back-office hanya mengenal kontrak ini; pelaksananya ada di
 * domain Pengelola dan diikat di penyedia layanan.
 *
 * Jenis yang diterima hanya `Email`, `Whatsapp`, dan `Penyimpanan`. Semua metode menolak di edisi SaaS.
 */
interface PengaturIntegrasiServer
{
    /** Jenis integrasi yang boleh diatur Owner edisi Lisensi. */
    public const JENIS = ['Email', 'Whatsapp', 'Penyimpanan'];

    /**
     * Satu baris per jenis untuk lingkungan server ini. Kredensial tidak pernah ikut, hanya petunjuknya (BR-P05.1).
     *
     * @return list<array<string, mixed>>
     */
    public function AmbilDaftar(): array;

    /**
     * Menyimpan penyedia, pengaturan, dan kredensial (kosong = pertahankan, BR-P05.6). Konfigurasi menjadi nonaktif
     * sampai diuji ulang (BR-P05.4). Isian tidak sah dilaporkan sebagai galat validasi per bidang.
     *
     * @param  array<string, mixed>  $pengaturan
     * @param  array<string, mixed>  $kredensial
     * @return array<string, mixed> nilai baru yang aman dicatat ke log audit (tanpa kredensial)
     */
    public function Simpan(string $jenis, string $penyedia, array $pengaturan, array $kredensial): array;

    /**
     * Menguji koneksi lalu langsung mengaktifkan bila berhasil.
     *
     * @return array{Berhasil: bool, Pesan: string}
     */
    public function UjiDanAktifkan(string $jenis): array;

    public function Nonaktifkan(string $jenis): void;
}
