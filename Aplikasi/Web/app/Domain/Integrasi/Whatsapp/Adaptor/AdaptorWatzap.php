<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Whatsapp\Adaptor;

use App\Domain\Integrasi\HasilUjiLayanan;
use App\Domain\Integrasi\Whatsapp\HasilKirimWhatsapp;
use App\Domain\Integrasi\Whatsapp\PesanWhatsapp;

/** Watzap.id (tidak resmi): `POST https://api.watzap.id/v1/send_message` dengan `api_key` + `number_key`. */
final class AdaptorWatzap extends AdaptorWhatsappDasar
{
    private const ALAMAT = 'https://api.watzap.id/v1';

    public function AmbilKode(): string
    {
        return 'Watzap';
    }

    public function UjiKoneksi(): HasilUjiLayanan
    {
        $respons = $this->Coba(fn () => $this->Http()->post(self::ALAMAT.'/checking_key', ['api_key' => $this->Kredensial('KunciApi')]));

        return $respons !== null && (string) $respons->json('status') === '200'
            ? HasilUjiLayanan::Berhasil('API key diterima Watzap.')
            : HasilUjiLayanan::Gagal($this->Saring('Watzap menolak API key: '.($respons?->json('message') ?? 'tidak ada jawaban')));
    }

    public function Kirim(PesanWhatsapp $pesan): HasilKirimWhatsapp
    {
        $respons = $this->Coba(fn () => $this->Http()->post(self::ALAMAT.'/send_message', [
            'api_key' => $this->Kredensial('KunciApi'),
            'number_key' => $this->Pengaturan('KunciNomor'),
            'phone_no' => $this->Nomor($pesan),
            'message' => $pesan->teks,
        ]));

        return $respons !== null && (string) $respons->json('status') === '200'
            ? HasilKirimWhatsapp::Berhasil(null)
            : $this->GagalRespons($respons, 'Pesan ditolak Watzap.');
    }
}
