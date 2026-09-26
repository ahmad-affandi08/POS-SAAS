<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Integrasi\Enum;

use App\Domain\Pengelola\Integrasi\Penguji\PengujiGerbangPembayaran;
use App\Domain\Pengelola\Integrasi\Penguji\PengujiKoneksi;
use App\Domain\Pengelola\Integrasi\Penguji\PengujiKoneksiPenyedia;
use App\Domain\Pengelola\Integrasi\Penguji\PengujiS3;
use App\Domain\Pengelola\Integrasi\Penguji\PengujiSmtp;
use App\Domain\Pengelola\Integrasi\Penguji\PengujiTurnstile;
use App\Domain\Pengelola\Integrasi\Penguji\PengujiWhatsapp;

/**
 * Katalog penyedia per jenis integrasi beserta bidang isiannya (P-05, v2.04). Tim platform tinggal memilih penyedia
 * lalu mengisi bidang; nilai `Bawaan` mengisi formulir otomatis (misal host SMTP). Bidang pengaturan tidak rahasia dan
 * boleh tampil; bidang kredensial disimpan terenkripsi dan tidak pernah ditampilkan ulang (BR-P05.1).
 *
 * - Email: semua penyedia lewat SMTP (relay SMTP resmi tiap penyedia), sehingga satu jalur kirim & satu penguji.
 * - Gerbang pembayaran: adaptor di `App\Domain\Integrasi\GerbangPembayaran` (QRIS dinamis).
 * - WhatsApp: resmi (WhatsApp Cloud API, Meta) atau tidak resmi berbasis WhatsApp Web (risiko nomor diblokir).
 */
enum PenyediaIntegrasi: string
{
    case Smtp = 'Smtp';
    case AmazonSes = 'AmazonSes';
    case Mailgun = 'Mailgun';
    case SendGrid = 'SendGrid';
    case Brevo = 'Brevo';
    case Postmark = 'Postmark';
    case Resend = 'Resend';
    case Mailjet = 'Mailjet';
    case Mailtrap = 'Mailtrap';
    case ZeptoMail = 'ZeptoMail';
    case ElasticEmail = 'ElasticEmail';
    case Gmail = 'Gmail';
    case Microsoft365 = 'Microsoft365';
    case ZohoMail = 'ZohoMail';
    case Hostinger = 'Hostinger';
    case Turnstile = 'Turnstile';
    case S3 = 'S3';
    case Midtrans = 'Midtrans';
    case Xendit = 'Xendit';
    case Tripay = 'Tripay';
    case Duitku = 'Duitku';
    case Ipaymu = 'Ipaymu';
    case Doku = 'Doku';
    case MetaCloud = 'MetaCloud';
    case Fonnte = 'Fonnte';
    case Wablas = 'Wablas';
    case StarSender = 'StarSender';
    case Watzap = 'Watzap';

    private const MODE = ['Kunci' => 'Mode', 'Label' => 'Mode', 'Jenis' => 'Pilihan', 'Wajib' => true, 'Opsi' => ['Sandbox', 'Produksi'], 'Bawaan' => 'Sandbox', 'Keterangan' => 'Sandbox untuk uji coba tanpa uang sungguhan.'];

    public function AmbilJenis(): JenisIntegrasi
    {
        return match ($this) {
            self::Turnstile => JenisIntegrasi::Captcha,
            self::S3 => JenisIntegrasi::Penyimpanan,
            self::Midtrans, self::Xendit, self::Tripay, self::Duitku, self::Ipaymu, self::Doku => JenisIntegrasi::GerbangPembayaran,
            self::MetaCloud, self::Fonnte, self::Wablas, self::StarSender, self::Watzap => JenisIntegrasi::Whatsapp,
            default => JenisIntegrasi::Email,
        };
    }

    /** Hanya WhatsApp: penyedia resmi Meta. Penyedia lain bernilai true. */
    public function CekResmi(): bool
    {
        return ! in_array($this, [self::Fonnte, self::Wablas, self::StarSender, self::Watzap], true);
    }

    /**
     * @return list<array{Kunci: string, Label: string, Jenis: string, Wajib: bool, Opsi?: list<string>, Bawaan?: string|int, Keterangan?: string}>
     */
    public function AmbilBidangPengaturan(): array
    {
        if ($this->AmbilJenis() === JenisIntegrasi::Email) {
            [$host, $port, $enkripsi, $pengguna] = $this->AmbilBawaanSmtp();

            return [
                ['Kunci' => 'Host', 'Label' => 'Host SMTP', 'Jenis' => 'Teks', 'Wajib' => true, 'Bawaan' => $host, 'Keterangan' => $host === '' ? 'Misal smtp.hostinger.com' : 'Terisi otomatis untuk penyedia ini.'],
                ['Kunci' => 'Port', 'Label' => 'Port', 'Jenis' => 'Angka', 'Wajib' => true, 'Bawaan' => $port, 'Keterangan' => '465 untuk SSL, 587/2525 untuk TLS'],
                ['Kunci' => 'Enkripsi', 'Label' => 'Enkripsi', 'Jenis' => 'Pilihan', 'Wajib' => true, 'Opsi' => ['Ssl', 'Tls'], 'Bawaan' => $enkripsi],
                ['Kunci' => 'NamaPengguna', 'Label' => 'Nama pengguna', 'Jenis' => 'Teks', 'Wajib' => true, 'Bawaan' => $pengguna],
                ['Kunci' => 'AlamatPengirim', 'Label' => 'Alamat pengirim', 'Jenis' => 'Email', 'Wajib' => true, 'Keterangan' => 'Domain pengirim harus sudah diverifikasi di penyedia (SPF/DKIM).'],
                ['Kunci' => 'NamaPengirim', 'Label' => 'Nama pengirim', 'Jenis' => 'Teks', 'Wajib' => true, 'Bawaan' => 'PAYOU'],
            ];
        }

        return match ($this) {
            self::Turnstile => [
                ['Kunci' => 'KunciSitus', 'Label' => 'Kunci situs (site key)', 'Jenis' => 'Teks', 'Wajib' => true, 'Keterangan' => 'Kunci publik yang dipasang di halaman registrasi'],
            ],
            self::S3 => [
                ['Kunci' => 'Endpoint', 'Label' => 'Endpoint', 'Jenis' => 'Url', 'Wajib' => true, 'Keterangan' => 'Misal https://<akun>.r2.cloudflarestorage.com'],
                ['Kunci' => 'Wilayah', 'Label' => 'Wilayah (region)', 'Jenis' => 'Teks', 'Wajib' => true, 'Keterangan' => 'Cloudflare R2: auto'],
                ['Kunci' => 'Bucket', 'Label' => 'Bucket', 'Jenis' => 'Teks', 'Wajib' => true],
            ],
            self::Midtrans => [
                self::MODE,
                ['Kunci' => 'Akuisitor', 'Label' => 'Akuisitor QRIS', 'Jenis' => 'Pilihan', 'Wajib' => true, 'Opsi' => ['gopay', 'airpay shopee'], 'Bawaan' => 'gopay'],
            ],
            self::Xendit => [],
            self::Tripay => [
                self::MODE,
                ['Kunci' => 'KodeMerchant', 'Label' => 'Kode merchant', 'Jenis' => 'Teks', 'Wajib' => true, 'Keterangan' => 'Misal T12345'],
                ['Kunci' => 'KanalQris', 'Label' => 'Kanal QRIS', 'Jenis' => 'Pilihan', 'Wajib' => true, 'Opsi' => ['QRIS', 'QRISC', 'QRIS2'], 'Bawaan' => 'QRIS', 'Keterangan' => 'Kode kanal QRIS yang aktif di akun Tripay.'],
            ],
            self::Duitku => [
                self::MODE,
                ['Kunci' => 'KodeMerchant', 'Label' => 'Kode merchant', 'Jenis' => 'Teks', 'Wajib' => true, 'Keterangan' => 'Misal D1234'],
                ['Kunci' => 'KanalQris', 'Label' => 'Kanal QRIS', 'Jenis' => 'Pilihan', 'Wajib' => true, 'Opsi' => ['SP', 'NQ', 'GQ', 'SQ'], 'Bawaan' => 'SP', 'Keterangan' => 'SP ShopeePay, NQ Nobu, GQ Gudang Voucher, SQ Nusapay.'],
            ],
            self::Ipaymu => [
                self::MODE,
                ['Kunci' => 'NomorVa', 'Label' => 'Nomor VA iPaymu', 'Jenis' => 'Teks', 'Wajib' => true, 'Keterangan' => 'Di menu Integrasi dasbor iPaymu.'],
            ],
            self::Doku => [
                self::MODE,
                ['Kunci' => 'IdKlien', 'Label' => 'Client ID', 'Jenis' => 'Teks', 'Wajib' => true, 'Keterangan' => 'DOKU Checkout menampilkan halaman bayar QRIS (QR berisi tautan halaman bayar).'],
            ],
            self::MetaCloud => [
                ['Kunci' => 'IdNomorTelepon', 'Label' => 'Phone number ID', 'Jenis' => 'Teks', 'Wajib' => true, 'Keterangan' => 'Dari WhatsApp Manager › API Setup.'],
                ['Kunci' => 'VersiApi', 'Label' => 'Versi Graph API', 'Jenis' => 'Teks', 'Wajib' => true, 'Bawaan' => 'v21.0'],
                ['Kunci' => 'NamaTemplatStruk', 'Label' => 'Nama templat struk', 'Jenis' => 'Teks', 'Wajib' => false, 'Keterangan' => 'Templat utilitas yang disetujui Meta dengan 3 variabel: {{1}} toko, {{2}} total, {{3}} tautan struk. Kosong = kirim teks (hanya dalam 24 jam setelah pelanggan mengirim pesan).'],
                ['Kunci' => 'BahasaTemplat', 'Label' => 'Kode bahasa templat', 'Jenis' => 'Teks', 'Wajib' => true, 'Bawaan' => 'id'],
            ],
            self::Fonnte, self::StarSender => [],
            self::Wablas => [
                ['Kunci' => 'Domain', 'Label' => 'Domain server Wablas', 'Jenis' => 'Url', 'Wajib' => true, 'Keterangan' => 'Misal https://jkt.wablas.com (lihat dasbor Wablas).'],
            ],
            self::Watzap => [
                ['Kunci' => 'KunciNomor', 'Label' => 'Number key', 'Jenis' => 'Teks', 'Wajib' => true, 'Keterangan' => 'Kunci nomor WhatsApp di dasbor Watzap.'],
            ],
            default => [],
        };
    }

    /**
     * @return list<array{Kunci: string, Label: string, Wajib: bool}>
     */
    public function AmbilBidangKredensial(): array
    {
        if ($this->AmbilJenis() === JenisIntegrasi::Email) {
            return [['Kunci' => 'KataSandi', 'Label' => $this->AmbilLabelKataSandi(), 'Wajib' => true]];
        }

        return match ($this) {
            self::Turnstile => [['Kunci' => 'KunciRahasia', 'Label' => 'Kunci rahasia (secret key)', 'Wajib' => true]],
            self::S3 => [
                ['Kunci' => 'IdKunciAkses', 'Label' => 'ID kunci akses (access key ID)', 'Wajib' => true],
                ['Kunci' => 'KunciAksesRahasia', 'Label' => 'Kunci akses rahasia (secret access key)', 'Wajib' => true],
            ],
            self::Midtrans => [['Kunci' => 'KunciServer', 'Label' => 'Server key', 'Wajib' => true]],
            self::Xendit => [
                ['Kunci' => 'KunciRahasia', 'Label' => 'Secret API key', 'Wajib' => true],
                ['Kunci' => 'TokenCallback', 'Label' => 'Token verifikasi callback', 'Wajib' => true],
            ],
            self::Tripay => [
                ['Kunci' => 'KunciApi', 'Label' => 'API key', 'Wajib' => true],
                ['Kunci' => 'KunciPrivat', 'Label' => 'Private key', 'Wajib' => true],
            ],
            self::Duitku, self::Ipaymu => [['Kunci' => 'KunciApi', 'Label' => 'API key', 'Wajib' => true]],
            self::Doku => [['Kunci' => 'KunciRahasia', 'Label' => 'Secret key', 'Wajib' => true]],
            self::MetaCloud => [['Kunci' => 'TokenAkses', 'Label' => 'Token akses permanen (system user)', 'Wajib' => true]],
            self::Fonnte => [['Kunci' => 'Token', 'Label' => 'Token perangkat Fonnte', 'Wajib' => true]],
            self::Wablas => [
                ['Kunci' => 'Token', 'Label' => 'Token Wablas', 'Wajib' => true],
                ['Kunci' => 'KunciRahasia', 'Label' => 'Secret key Wablas (bila diaktifkan)', 'Wajib' => false],
            ],
            self::StarSender, self::Watzap => [['Kunci' => 'KunciApi', 'Label' => 'API key', 'Wajib' => true]],
            default => [],
        };
    }

    /**
     * @return class-string<PengujiKoneksi>|class-string<PengujiKoneksiPenyedia>
     */
    public function AmbilKelasPenguji(): string
    {
        return match ($this->AmbilJenis()) {
            JenisIntegrasi::Email => PengujiSmtp::class,
            JenisIntegrasi::Captcha => PengujiTurnstile::class,
            JenisIntegrasi::Penyimpanan => PengujiS3::class,
            JenisIntegrasi::GerbangPembayaran => PengujiGerbangPembayaran::class,
            JenisIntegrasi::Whatsapp => PengujiWhatsapp::class,
        };
    }

    public function AmbilLabel(): string
    {
        return match ($this) {
            self::Smtp => 'SMTP (server sendiri / hosting)',
            self::AmazonSes => 'Amazon SES',
            self::Mailgun => 'Mailgun',
            self::SendGrid => 'Twilio SendGrid',
            self::Brevo => 'Brevo (Sendinblue)',
            self::Postmark => 'Postmark',
            self::Resend => 'Resend',
            self::Mailjet => 'Mailjet',
            self::Mailtrap => 'Mailtrap Email Sending',
            self::ZeptoMail => 'Zoho ZeptoMail',
            self::ElasticEmail => 'Elastic Email',
            self::Gmail => 'Gmail / Google Workspace',
            self::Microsoft365 => 'Microsoft 365 / Outlook',
            self::ZohoMail => 'Zoho Mail',
            self::Hostinger => 'Hostinger Email',
            self::Turnstile => 'Cloudflare Turnstile',
            self::S3 => 'S3-compatible (misal Cloudflare R2)',
            self::Midtrans => 'Midtrans',
            self::Xendit => 'Xendit',
            self::Tripay => 'Tripay',
            self::Duitku => 'Duitku',
            self::Ipaymu => 'iPaymu',
            self::Doku => 'DOKU',
            self::MetaCloud => 'WhatsApp Cloud API (resmi, Meta)',
            self::Fonnte => 'Fonnte (tidak resmi)',
            self::Wablas => 'Wablas (tidak resmi)',
            self::StarSender => 'StarSender (tidak resmi)',
            self::Watzap => 'Watzap (tidak resmi)',
        };
    }

    public function AmbilKeterangan(): string
    {
        return match ($this) {
            self::Smtp => 'Server SMTP apa pun, misal dari hosting.',
            self::AmazonSes => 'Region Jakarta (ap-southeast-3); ganti host bila memakai region lain. Pakai kredensial SMTP SES, bukan access key IAM.',
            self::Mailgun => 'Pakai smtp.eu.mailgun.org untuk akun region EU.',
            self::SendGrid => 'Nama pengguna selalu "apikey".',
            self::Brevo => 'Nama pengguna = login SMTP di menu SMTP & API.',
            self::Postmark => 'Nama pengguna dan kata sandi sama-sama server API token.',
            self::Resend => 'Nama pengguna selalu "resend".',
            self::Mailjet => 'Nama pengguna = API key Mailjet.',
            self::Mailtrap => 'Untuk pengiriman nyata (bukan sandbox).',
            self::ZeptoMail => 'Nama pengguna selalu "emailapikey".',
            self::ElasticEmail => 'Nama pengguna = email akun Elastic Email.',
            self::Gmail => 'Wajib verifikasi 2 langkah + sandi aplikasi. Batas kirim harian Google berlaku.',
            self::Microsoft365 => 'SMTP AUTH harus diaktifkan untuk kotak surat ini.',
            self::ZohoMail => 'Pakai smtp.zoho.com.au/.eu sesuai pusat data akun.',
            self::Hostinger => '',
            self::Midtrans => 'QRIS dinamis lewat Core API. Atur URL notifikasi di dasbor Midtrans ke /webhook/midtrans.',
            self::Xendit => 'QRIS dinamis lewat QR Codes API. Atur URL callback QR di dasbor Xendit ke /webhook/xendit.',
            self::Tripay => 'QRIS dinamis lewat transaksi closed payment. URL callback dikirim otomatis per transaksi.',
            self::Duitku => 'QRIS dinamis lewat API v2. URL callback dikirim otomatis per transaksi.',
            self::Ipaymu => 'QRIS dinamis lewat direct payment. Notifikasi dikonfirmasi ulang ke iPaymu sebelum dipercaya.',
            self::Doku => 'DOKU Checkout (halaman bayar QRIS). Atur URL notifikasi di dasbor DOKU ke /webhook/doku.',
            self::MetaCloud => 'Resmi dan aman dari pemblokiran. Di luar 24 jam percakapan wajib memakai templat yang disetujui Meta (berbayar per percakapan).',
            self::Fonnte, self::Wablas, self::StarSender, self::Watzap => 'Tidak resmi (WhatsApp Web): murah dan mudah, tetapi nomor bisa diblokir WhatsApp bila mengirim massal. Pakai nomor khusus, bukan nomor utama usaha.',
            default => '',
        };
    }

    /**
     * @return array{0: string, 1: int, 2: string, 3: string}
     */
    private function AmbilBawaanSmtp(): array
    {
        return match ($this) {
            self::Smtp => ['', 587, 'Tls', ''],
            self::AmazonSes => ['email-smtp.ap-southeast-3.amazonaws.com', 587, 'Tls', ''],
            self::Mailgun => ['smtp.mailgun.org', 587, 'Tls', 'postmaster@domain-anda'],
            self::SendGrid => ['smtp.sendgrid.net', 587, 'Tls', 'apikey'],
            self::Brevo => ['smtp-relay.brevo.com', 587, 'Tls', ''],
            self::Postmark => ['smtp.postmarkapp.com', 587, 'Tls', ''],
            self::Resend => ['smtp.resend.com', 465, 'Ssl', 'resend'],
            self::Mailjet => ['in-v3.mailjet.com', 587, 'Tls', ''],
            self::Mailtrap => ['live.smtp.mailtrap.io', 587, 'Tls', 'api'],
            self::ZeptoMail => ['smtp.zeptomail.com', 587, 'Tls', 'emailapikey'],
            self::ElasticEmail => ['smtp.elasticemail.com', 2525, 'Tls', ''],
            self::Gmail => ['smtp.gmail.com', 465, 'Ssl', ''],
            self::Microsoft365 => ['smtp.office365.com', 587, 'Tls', ''],
            self::ZohoMail => ['smtp.zoho.com', 465, 'Ssl', ''],
            self::Hostinger => ['smtp.hostinger.com', 465, 'Ssl', ''],
            default => ['', 587, 'Tls', ''],
        };
    }

    private function AmbilLabelKataSandi(): string
    {
        return match ($this) {
            self::Smtp => 'Kata sandi SMTP',
            self::AmazonSes => 'Kata sandi SMTP SES',
            self::Mailgun => 'Kata sandi SMTP Mailgun',
            self::SendGrid => 'API key SendGrid',
            self::Brevo => 'Kunci SMTP Brevo',
            self::Postmark => 'Server API token',
            self::Resend => 'API key Resend',
            self::Mailjet => 'Secret key Mailjet',
            self::Mailtrap => 'API token Mailtrap',
            self::ZeptoMail => 'Token kirim ZeptoMail',
            self::ElasticEmail => 'API key Elastic Email',
            self::Gmail => 'Sandi aplikasi Google',
            self::Microsoft365 => 'Kata sandi akun',
            self::ZohoMail => 'Kata sandi / sandi aplikasi Zoho',
            self::Hostinger => 'Kata sandi email Hostinger',
            default => 'Kata sandi',
        };
    }
}
