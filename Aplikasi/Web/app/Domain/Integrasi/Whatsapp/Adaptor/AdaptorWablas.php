<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Whatsapp\Adaptor;

use App\Domain\Integrasi\HasilUjiLayanan;
use App\Domain\Integrasi\Whatsapp\HasilKirimWhatsapp;
use App\Domain\Integrasi\Whatsapp\PesanWhatsapp;

/**
 * Wablas (tidak resmi): server per akun (`https://{kota}.wablas.com`), header `Authorization: {token}` atau
 * `{token}.{kunci rahasia}` untuk akun yang memakai kunci rahasia.
 */
final class AdaptorWablas extends AdaptorWhatsappDasar
{
    public function AmbilKode(): string
    {
        return 'Wablas';
    }

    private function Otorisasi(): string
    {
        $rahasia = $this->Kredensial('KunciRahasia');

        return $rahasia === '' ? $this->Kredensial('Token') : $this->Kredensial('Token').'.'.$rahasia;
    }

    private function Alamat(string $jalur): string
    {
        return rtrim($this->Pengaturan('Domain'), '/').$jalur;
    }

    public function UjiKoneksi(): HasilUjiLayanan
    {
        $respons = $this->Coba(fn () => $this->Http()->get($this->Alamat('/api/device/info'), ['token' => $this->Kredensial('Token')]));

        return $respons !== null && $respons->json('status') === true
            ? HasilUjiLayanan::Berhasil('Token diterima Wablas (perangkat: '.($respons->json('data.status') ?? 'tidak diketahui').').')
            : HasilUjiLayanan::Gagal($this->Saring('Wablas menolak token atau domain: '.($respons?->json('message') ?? 'tidak ada jawaban')));
    }

    public function Kirim(PesanWhatsapp $pesan): HasilKirimWhatsapp
    {
        $respons = $this->Coba(fn () => $this->Http()->withHeaders(['Authorization' => $this->Otorisasi()])->asForm()
            ->post($this->Alamat('/api/send-message'), ['phone' => $this->Nomor($pesan), 'message' => $pesan->teks]));
        $id = $respons?->json('data.messages.0.id');

        return $respons?->json('status') === true
            ? HasilKirimWhatsapp::Berhasil(is_scalar($id) ? (string) $id : null)
            : $this->GagalRespons($respons, 'Pesan ditolak Wablas.');
    }
}
