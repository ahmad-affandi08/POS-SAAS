<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Whatsapp;

use App\Domain\Integrasi\HasilUjiLayanan;

/**
 * Port pengirim WhatsApp (PRD §16.6). Penyedia dipilih di konsol Platform Pengelola (P-05): WhatsApp Cloud API
 * (resmi, Meta) atau penyedia tidak resmi berbasis WhatsApp Web (Fonnte, Wablas, StarSender, Watzap). Tidak pernah
 * melempar exception untuk kegagalan kirim; hasil dikembalikan.
 */
interface PengirimWhatsapp
{
    public function AmbilKode(): string;

    public function CekResmi(): bool;

    public function UjiKoneksi(): HasilUjiLayanan;

    public function Kirim(PesanWhatsapp $pesan): HasilKirimWhatsapp;
}
