<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\Operasional\Kueri;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Job gagal dari tabel `failed_jobs` bawaan Laravel (P-11). Payload job **tidak pernah** ditampilkan utuh: isi
 * `data.command` (objek job terserialisasi, bisa memuat token, tautan bertanda tangan, atau data pribadi) dibuang,
 * hanya metadata yang ditampilkan. Pesan galat disaring dari pola rahasia dan dipotong.
 */
final class TugasGagal
{
    /** Kunci payload yang aman ditampilkan. */
    private const KUNCI_PAYLOAD_AMAN = [
        'uuid', 'displayName', 'job', 'maxTries', 'maxExceptions', 'failOnTimeout', 'backoff', 'timeout', 'retryUntil',
        'attempts', 'createdAt', 'pushedAt', 'tags',
    ];

    private const BARIS_JEJAK_MAKSIMAL = 20;

    /**
     * @return list<array{Uuid: string, Koneksi: string, Antrean: string, NamaTugas: string, RingkasanGalat: string, GagalPada: string}>
     */
    public function AmbilDaftar(int $jumlah): array
    {
        return array_values($this->AmbilTabel()
            ->orderByDesc('id')
            ->limit($jumlah)
            ->get(['uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'])
            ->map(fn (object $baris): array => [
                'Uuid' => (string) $baris->uuid,
                'Koneksi' => (string) $baris->connection,
                'Antrean' => (string) $baris->queue,
                'NamaTugas' => self::AmbilNamaTugas((string) $baris->payload),
                'RingkasanGalat' => Str::limit(self::SaringRahasia(Str::before((string) $baris->exception, "\n")), 200),
                'GagalPada' => Carbon::parse((string) $baris->failed_at)->toIso8601String(),
            ])
            ->all());
    }

    public function Hitung(): int
    {
        return $this->AmbilTabel()->count();
    }

    /**
     * @return array{Uuid: string, Koneksi: string, Antrean: string, NamaTugas: string, Payload: array<string, mixed>, Galat: string, GagalPada: string}|null
     */
    public function AmbilDetail(string $uuid): ?array
    {
        $baris = $this->AmbilTabel()->where('uuid', $uuid)->first(['uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at']);

        if ($baris === null) {
            return null;
        }

        $payload = json_decode((string) $baris->payload, true);
        $payload = is_array($payload) ? $payload : [];
        $aman = array_intersect_key($payload, array_flip(self::KUNCI_PAYLOAD_AMAN));
        $namaKelas = $payload['data']['commandName'] ?? null;

        if (is_string($namaKelas)) {
            $aman['commandName'] = $namaKelas;
        }

        $jejak = array_slice(explode("\n", (string) $baris->exception), 0, self::BARIS_JEJAK_MAKSIMAL);

        return [
            'Uuid' => (string) $baris->uuid,
            'Koneksi' => (string) $baris->connection,
            'Antrean' => (string) $baris->queue,
            'NamaTugas' => self::AmbilNamaTugas((string) $baris->payload),
            'Payload' => $aman,
            'Galat' => self::SaringRahasia(implode("\n", $jejak)),
            'GagalPada' => Carbon::parse((string) $baris->failed_at)->toIso8601String(),
        ];
    }

    /** Menyamarkan nilai yang tampak seperti rahasia: kata sandi, token, kunci, header Authorization, kredensial di URL. */
    public static function SaringRahasia(string $teks): string
    {
        $teks = (string) preg_replace('#(://[^/\s:@]+:)[^@\s/]+@#', '$1[disembunyikan]@', $teks);
        $teks = (string) preg_replace('#\b(Bearer|Basic)\s+[A-Za-z0-9._~+/=-]+#i', '$1 [disembunyikan]', $teks);

        return (string) preg_replace(
            '#\b((?:kata_?sandi|password|passwd|pwd|secret|rahasia|token|api[_-]?key|kunci|signature|tanda_?tangan)["\']?\s*[:=]\s*["\']?)[^\s"\'&,;]+#i',
            '$1[disembunyikan]',
            $teks,
        );
    }

    private static function AmbilNamaTugas(string $payload): string
    {
        $data = json_decode($payload, true);
        $nama = is_array($data) ? ($data['displayName'] ?? null) : null;

        return is_string($nama) ? $nama : '(tidak dikenal)';
    }

    private function AmbilTabel(): Builder
    {
        $koneksi = config('queue.failed.database');

        return DB::connection(is_string($koneksi) ? $koneksi : null)->table((string) config('queue.failed.table', 'failed_jobs'));
    }
}
