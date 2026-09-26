<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Whatsapp\Adaptor;

use App\Domain\Integrasi\HasilUjiLayanan;
use App\Domain\Integrasi\Whatsapp\HasilKirimWhatsapp;
use App\Domain\Integrasi\Whatsapp\PesanWhatsapp;

/**
 * WhatsApp Business Cloud API resmi (Meta Graph `/{phone-number-id}/messages`). Pesan dengan [namaTemplat] dikirim
 * sebagai templat (wajib di luar jendela 24 jam); tanpa templat dikirim sebagai teks.
 */
final class AdaptorMetaCloud extends AdaptorWhatsappDasar
{
    public function AmbilKode(): string
    {
        return 'MetaCloud';
    }

    public function CekResmi(): bool
    {
        return true;
    }

    private function Alamat(string $jalur = ''): string
    {
        $versi = $this->Pengaturan('VersiApi') !== '' ? $this->Pengaturan('VersiApi') : 'v21.0';

        return "https://graph.facebook.com/{$versi}/".rawurlencode($this->Pengaturan('IdNomorTelepon')).$jalur;
    }

    public function UjiKoneksi(): HasilUjiLayanan
    {
        $respons = $this->Coba(fn () => $this->Http()->withToken($this->Kredensial('TokenAkses'))
            ->get($this->Alamat(), ['fields' => 'display_phone_number,verified_name']));

        if ($respons === null) {
            return HasilUjiLayanan::Gagal('Graph API Meta tidak bisa dihubungi.');
        }

        $nomor = $respons->json('display_phone_number');

        return $respons->successful() && is_string($nomor)
            ? HasilUjiLayanan::Berhasil("Terhubung ke nomor {$nomor} ({$respons->json('verified_name')}).")
            : HasilUjiLayanan::Gagal($this->Saring('Meta menolak token/ID nomor: '.($respons->json('error.message') ?? "HTTP {$respons->status()}")));
    }

    public function Kirim(PesanWhatsapp $pesan): HasilKirimWhatsapp
    {
        $isi = ['messaging_product' => 'whatsapp', 'to' => $this->Nomor($pesan)];

        if ($pesan->namaTemplat !== null && $pesan->namaTemplat !== '') {
            $isi += ['type' => 'template', 'template' => [
                'name' => $pesan->namaTemplat,
                'language' => ['code' => $this->Pengaturan('BahasaTemplat') !== '' ? $this->Pengaturan('BahasaTemplat') : 'id'],
                'components' => $pesan->parameterTemplat === [] ? [] : [[
                    'type' => 'body',
                    'parameters' => array_map(fn (string $nilai): array => ['type' => 'text', 'text' => $nilai], $pesan->parameterTemplat),
                ]],
            ]];
        } else {
            $isi += ['type' => 'text', 'text' => ['preview_url' => true, 'body' => $pesan->teks]];
        }

        $respons = $this->Coba(fn () => $this->Http()->withToken($this->Kredensial('TokenAkses'))->post($this->Alamat('/messages'), $isi));
        $id = $respons?->json('messages.0.id');

        return $respons !== null && $respons->successful() && is_string($id)
            ? HasilKirimWhatsapp::Berhasil($id)
            : $this->GagalRespons($respons, 'Pesan ditolak Meta.');
    }
}
