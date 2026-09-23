<?php

declare(strict_types=1);

use App\Domain\Bersama\Galat\PelanggaranAturanBisnis;
use App\Http\Kontroler\Pengelola\GalatKontroler;
use App\Http\Perantara\Pengelola\SiapkanSesiPengelola;
use App\Http\Respons\GalatApi;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/sehat',
        // F-02b: API Aplikasi POS (device token, tanpa sesi), prefix /api/pos/v1, nama rute pos.*.
        then: function (): void {
            Route::middleware('api')->prefix('api/pos/v1')->group(base_path('routes/Pos.php'));
        },
    )
    ->withCommands([__DIR__.'/../app/Console/Perintah'])
    ->withMiddleware(function (Middleware $middleware): void {
        // Harus berjalan sebelum StartSession: cookie sesi pengelola terpisah dari tenant (BR-P01.4).
        $middleware->prepend(SiapkanSesiPengelola::class);

        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->getHost() === config('pengelola.Domain') ? route('pengelola.masuk') : route('masuk'),
        );
        $middleware->redirectUsersTo(
            fn (Request $request) => $request->getHost() === config('pengelola.Domain') ? route('pengelola.beranda') : route('kelola.beranda'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Galat di subdomain pengelola ditampilkan sebagai halaman berbahasa Indonesia (PRD §17.6.6).
        $exceptions->respond(fn (SymfonyResponse $respons, Throwable $galat, Request $request) => GalatKontroler::UbahRespons($respons, $request));

        // F-02b: galat framework di API (validasi, 404, 429, ...) juga berformat {"Galat": {...}} (PRD §16.2).
        $exceptions->render(fn (Throwable $galat, Request $request) => $request->is('api/*') ? GalatApi::DariGalat($galat) : null);

        $exceptions->dontFlash(['KataSandi', 'KonfirmasiKataSandi', 'Kode']);

        // Pelanggaran aturan bisnis → galat validasi (Inertia) atau format galat seragam (JSON), PRD §16.
        $exceptions->render(function (PelanggaranAturanBisnis $galat, Request $request) {
            if ($request->is('api/*') || ($request->expectsJson() && ! $request->hasHeader('X-Inertia'))) {
                // F-02b: status & detail opsional (misal PIN terkunci 429), bawaan 422.
                return GalatApi::Buat($galat->kode, $galat->getMessage(), $galat->statusHttp, $galat->detail);
            }

            return back()
                ->withInput($request->except(['KataSandi', 'KonfirmasiKataSandi', 'Kode']))
                ->withErrors([$galat->bidang => $galat->getMessage()]);
        });
    })->create();
