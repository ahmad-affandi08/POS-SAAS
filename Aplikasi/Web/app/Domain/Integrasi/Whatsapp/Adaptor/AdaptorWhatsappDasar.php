<?php

declare(strict_types=1);

namespace App\Domain\Integrasi\Whatsapp\Adaptor;

use App\Domain\Integrasi\Whatsapp\HasilKirimWhatsapp;
use App\Domain\Integrasi\Whatsapp\PengirimWhatsapp;
use App\Domain\Integrasi\Whatsapp\PesanWhatsapp;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

abstract class AdaptorWhatsappDasar implements PengirimWhatsapp
{
    /**
     * @param  array<string, string|int>  $pengaturan
     * @param  array<string, string>  $kredensial
     */
    public function __construct(protected readonly array $pengaturan, protected readonly array $kredensial) {}

    public function CekResmi(): bool
    {
        return false;
    }

    protected function Pengaturan(string $kunci): string
    {
        return trim((string) ($this->pengaturan[$kunci] ?? ''));
    }

    protected function Kredensial(string $kunci): string
    {
        return $this->kredensial[$kunci] ?? '';
    }

    protected function Http(): PendingRequest
    {
        return Http::timeout(15)->acceptJson();
    }

    /**
     * @param  callable(): Response  $kirim
     */
    protected function Coba(callable $kirim): ?Response
    {
        try {
            return $kirim();
        } catch (ConnectionException) {
            return null;
        }
    }

    protected function Saring(string $pesan): string
    {
        foreach ($this->kredensial as $rahasia) {
            if ($rahasia !== '') {
                $pesan = str_replace($rahasia, '••••', $pesan);
            }
        }

        return $pesan;
    }

    protected function GagalRespons(?Response $respons, string $bawaan): HasilKirimWhatsapp
    {
        if ($respons === null) {
            return HasilKirimWhatsapp::Gagal('Penyedia WhatsApp tidak bisa dihubungi.');
        }

        $pesan = $respons->json('message') ?? $respons->json('reason') ?? $respons->json('detail') ?? $respons->json('error.message') ?? $bawaan;

        return HasilKirimWhatsapp::Gagal($this->Saring(is_string($pesan) ? $pesan : $bawaan));
    }

    protected function Nomor(PesanWhatsapp $pesan): string
    {
        return PesanWhatsapp::RapikanNomor($pesan->nomor);
    }
}
