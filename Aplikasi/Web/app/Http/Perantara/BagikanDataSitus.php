<?php

declare(strict_types=1);

namespace App\Http\Perantara;

use App\Domain\Situs\Kueri\PenyusunHalamanSitus;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Perantara Inertia situs pemasaran (D-21): view root `Situs` (bundle `Situs.tsx` + meta SEO dari server) dan data
 * bersama semua halaman situs (menu, kaki, kontak, media sosial, pengumuman, tombol masuk/daftar).
 */
final class BagikanDataSitus extends Middleware
{
    protected $rootView = 'Situs';

    public function __construct(private readonly PenyusunHalamanSitus $penyusun) {}

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'Situs' => fn (): array => $this->penyusun->AmbilDataBersama(),
        ];
    }
}
