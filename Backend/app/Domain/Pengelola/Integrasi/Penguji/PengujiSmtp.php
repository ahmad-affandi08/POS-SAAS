<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Penguji;

use App\Domain\Pengelola\Integrasi\Data\HasilUjiKoneksi;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Throwable;

/**
 * Membuka sesi SMTP dan login tanpa mengirim email (P-05).
 */
final class PengujiSmtp implements PengujiKoneksi
{
    public function Uji(array $pengaturan, array $kredensial): HasilUjiKoneksi
    {
        try {
            $transport = new EsmtpTransport(
                (string) ($pengaturan['Host'] ?? ''),
                (int) ($pengaturan['Port'] ?? 0),
                ($pengaturan['Enkripsi'] ?? '') === 'Ssl',
            );
            $transport->setUsername((string) ($pengaturan['NamaPengguna'] ?? ''));
            $transport->setPassword($kredensial['KataSandi'] ?? '');
            $aliran = $transport->getStream();

            if ($aliran instanceof SocketStream) {
                $aliran->setTimeout(10);
            }

            $transport->start();
            $transport->stop();

            return HasilUjiKoneksi::Berhasil('Login SMTP berhasil.');
        } catch (Throwable $galat) {
            return HasilUjiKoneksi::Gagal('Tidak bisa login ke server SMTP: '.PenyaringPesan::Saring($galat->getMessage(), $kredensial));
        }
    }
}
