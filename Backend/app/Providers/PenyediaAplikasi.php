<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Bersama\Database\MakroSkema;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Pengelola\Integrasi\Layanan\PenerapKonfigurasiIntegrasi;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use LogicException;

final class PenyediaAplikasi extends ServiceProvider
{
    public function register(): void
    {
        // "scoped": dibuat ulang untuk setiap request/job sehingga tenant tidak terbawa antar-request.
        $this->app->scoped(KonteksTenant::class);
        $this->app->scoped(PencatatAuditPengelola::class);
    }

    public function boot(): void
    {
        MakroSkema::Daftarkan();

        // Pabrik model berada di Database\Pabrik dengan akhiran "Pabrik" (PRD §13.7).
        Factory::guessFactoryNamesUsing(static function (string $model): string {
            $kelasPabrik = 'Database\\Pabrik\\'.class_basename($model).'Pabrik';

            if (! is_subclass_of($kelasPabrik, Factory::class)) {
                throw new LogicException("Pabrik {$kelasPabrik} untuk model {$model} tidak ditemukan.");
            }

            return $kelasPabrik;
        });

        // Mencegah lazy loading (N+1), atribut tak dikenal, dan mass assignment diam-diam saat pengembangan.
        Model::shouldBeStrict(! $this->app->isProduction());

        // BR-00.4: batas percobaan registrasi per IP; pesan tampil di formulir, bukan halaman galat 429.
        RateLimiter::for('pendaftaran', static fn (Request $permintaan) => Limit::perHour((int) config('tenant.BatasRegistrasiPerJam'))
            ->by((string) $permintaan->ip())
            ->response(static fn () => back()->withErrors(['Umum' => 'Terlalu banyak percobaan pendaftaran dari jaringan ini. Coba lagi dalam satu jam.'])));

        // P-05: email, CAPTCHA, dan penyimpanan objek memakai konfigurasi aktif dari Platform Pengelola.
        $this->app->make(PenerapKonfigurasiIntegrasi::class)->Terapkan();

        if ($this->app->runningUnitTests()) {
            $this->loadMigrationsFrom(base_path('tests/Pendukung/Migrasi'));
        }
    }
}
