<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Whatsapp\Adaptor;

use App\Domain\Integrasi\HasilUjiLayanan;
use App\Domain\Integrasi\Whatsapp\HasilKirimWhatsapp;
use App\Domain\Integrasi\Whatsapp\PesanWhatsapp;

/** StarSender (tidak resmi): `POST https://api.starsender.online/api/send`, header `Authorization: {API key perangkat}`. */
final class AdaptorStarSender extends AdaptorWhatsappDasar
{
    private const ALAMAT = 'https://api.starsender.online/api';

    public function AmbilKode(): string
    {
        return 'StarSender';
    }

    public function UjiKoneksi(): HasilUjiLayanan
    {
        $respons = $this->Coba(fn () => $this->Http()->withHeaders(['Authorization' => $this->Kredensial('KunciApi')])->get(self::ALAMAT.'/devices'));

        return $respons !== null && $respons->successful() && $respons->json('success') !== false
            ? HasilUjiLayanan::Berhasil('API key diterima StarSender.')
            : HasilUjiLayanan::Gagal($this->Saring('StarSender menolak API key: '.($respons?->json('message') ?? 'tidak ada jawaban')));
    }

    public function Kirim(PesanWhatsapp $pesan): HasilKirimWhatsapp
    {
        $respons = $this->Coba(fn () => $this->Http()->withHeaders(['Authorization' => $this->Kredensial('KunciApi')])
            ->post(self::ALAMAT.'/send', ['messageType' => 'text', 'to' => $this->Nomor($pesan), 'body' => $pesan->teks]));

        return $respons !== null && $respons->successful() && $respons->json('success') === true
            ? HasilKirimWhatsapp::Berhasil(is_scalar($respons->json('data.id')) ? (string) $respons->json('data.id') : null)
            : $this->GagalRespons($respons, 'Pesan ditolak StarSender.');
    }
}
