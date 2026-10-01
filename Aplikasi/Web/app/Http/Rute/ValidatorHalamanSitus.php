<?php

declare(strict_types=1);

namespace App\Http\Rute;

use App\Domain\Situs\Kueri\CekHalamanSitusTerbit;
use Illuminate\Http\Request;
use Illuminate\Routing\Matching\ValidatorInterface;
use Illuminate\Routing\Route;

/**
 * Rute `situs.halaman` (`/{slugHalaman}`) hanya cocok bila slug-nya memang halaman situs pemasaran yang terbit.
 * Jalur satu segmen yang lain (`/{slugTenant}` toko online) tetap dilayani rute lain yang terdaftar sesudahnya.
 * Tanpa ini, rute toko online yang menangkap semua slug akan menutupi `/fitur`, `/harga`, dan halaman situs lain
 * (D-21 vs F-17), karena pola regex saja tidak bisa membedakan slug tenant dari slug halaman.
 */
final class ValidatorHalamanSitus implements ValidatorInterface
{
    public const NAMA_RUTE = 'situs.halaman';

    public function matches(Route $route, Request $request): bool
    {
        if ($route->getName() !== self::NAMA_RUTE) {
            return true;
        }

        return app(CekHalamanSitusTerbit::class)->Ada(rawurldecode(trim($request->getPathInfo(), '/')));
    }
}
