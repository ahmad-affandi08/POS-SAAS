<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Galat;

use RuntimeException;

/**
 * Email atau nomor WhatsApp pendaftar sudah dipakai akun lain (BR-00.1). Sengaja bukan `PelanggaranAturanBisnis`:
 * rinciannya (akun mana, identitas apa) tidak boleh sampai ke pendaftar (§25 no. 18). Pemanggil menampilkan pesan
 * umum `PESAN_UMUM` dan memberi tahu pemilik akun lewat `BeritahuUpayaPendaftaranGanda`.
 */
final class IdentitasSudahTerdaftar extends RuntimeException
{
    public const PESAN_UMUM = 'Email atau nomor WhatsApp tidak dapat dipakai. Jika ini milik Anda, masuk atau atur ulang kata sandi.';

    /**
     * @param  array<int, list<string>>  $identitasPerPengguna  IdPengguna => label identitas yang cocok ("email", "nomor WhatsApp")
     */
    public function __construct(public readonly array $identitasPerPengguna)
    {
        parent::__construct('Email atau nomor WhatsApp sudah dipakai akun lain.');
    }
}
