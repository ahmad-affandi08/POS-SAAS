<?php

declare(strict_types=1);

namespace App\Http\Kontroler\Publik;

use App\Http\Kontroler\Kontroler;
use Inertia\Inertia;
use Inertia\Response;

/**
 * X7 bagian 3: portal dokumentasi pengembang (`/pengembang`, domain pemasaran). Daftar endpoint, cakupan, dan
 * peristiwa webhook dibaca dari spesifikasi OpenAPI yang sama dengan berkas unduhan `public/pengembang/openapi-v1.json`,
 * sehingga halaman tidak bisa menyimpang dari spesifikasi (dan spesifikasi dijaga terhadap rute & respons nyata oleh
 * `SpesifikasiApiPublikTes`).
 */
final class PengembangKontroler extends Kontroler
{
    public const BERKAS_SPESIFIKASI = 'pengembang/openapi-v1.json';

    public function Tampilkan(): Response
    {
        /** @var array<string, mixed> $spesifikasi */
        $spesifikasi = json_decode((string) file_get_contents(public_path(self::BERKAS_SPESIFIKASI)), true, flags: JSON_THROW_ON_ERROR);
        $endpoint = [];

        foreach ((array) $spesifikasi['paths'] as $jalur => $metode) {
            foreach ((array) $metode as $nama => $op) {
                $op = (array) $op;
                $endpoint[] = [
                    'Metode' => strtoupper((string) $nama),
                    'Jalur' => '/api/v1'.$jalur,
                    'Ringkasan' => (string) $op['summary'],
                    'Cakupan' => (string) (((array) ((array) $op['security'])[0])['TokenApi'][0] ?? ''),
                    'Parameter' => array_values(array_map(
                        fn (array $p): array => ['Nama' => (string) $p['name'], 'Wajib' => (bool) ($p['required'] ?? false)],
                        array_filter((array) ($op['parameters'] ?? []), fn (mixed $p): bool => is_array($p) && isset($p['name'])),
                    )),
                ];
            }
        }

        $webhook = [];

        foreach ((array) $spesifikasi['webhooks'] as $nama => $isi) {
            $webhook[] = ['Peristiwa' => (string) $nama, 'Ringkasan' => (string) explode(' Header:', (string) ((array) ((array) $isi)['post'])['description'])[0]];
        }

        return Inertia::render('Situs/Pengembang', [
            'AlamatApi' => url('/api/v1'),
            'Versi' => (string) ((array) $spesifikasi['info'])['version'],
            'Endpoint' => $endpoint,
            'Webhook' => $webhook,
            'UnduhSpesifikasi' => '/'.self::BERKAS_SPESIFIKASI,
        ]);
    }
}
