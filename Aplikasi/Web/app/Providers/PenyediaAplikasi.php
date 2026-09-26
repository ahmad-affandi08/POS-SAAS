<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Bersama\Audit\Layanan\PencatatAudit;
use App\Domain\Bersama\Database\MakroSkema;
use App\Domain\Bersama\Sinkron\Layanan\PenandaSinkronPos;
use App\Domain\Bersama\Tenant\KonteksTenant;
use App\Domain\Dukungan\Peristiwa\TiketDukunganDibalasPelapor;
use App\Domain\Dukungan\Peristiwa\TiketDukunganDibuat;
use App\Domain\Organisasi\Model\Perangkat;
use App\Domain\Pengelola\Dukungan\Penangan\BeritahuPenanggungJawabBalasanPelapor;
use App\Domain\Pengelola\Dukungan\Penangan\BeritahuTimTiketDukunganBaru;
use App\Domain\Pengelola\Integrasi\Layanan\PenerapKonfigurasiIntegrasi;
use App\Domain\Pengelola\Operasional\Penangan\PeriksaOperasionalSaatCekSehat;
use App\Domain\Pengelola\Tenant\Layanan\KonteksPengelola;
use App\Domain\Pengelola\TimInternal\Layanan\PencatatAuditPengelola;
use App\Http\Perantara\AutentikasiPerangkat;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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

        // P-09: KonteksPengelola menyimpan status "di dalam JalankanLintasTenant" per request/job.
        $this->app->scoped(KonteksPengelola::class);
        // F-02: pencatat log audit tenant (pelaku & IP diisi perantara per request).
        $this->app->scoped(PencatatAudit::class);
        $this->app->scoped(PenandaSinkronPos::class);
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

        // API POS: batas per rute dan per perangkat (bukan per IP bersama). Perangkat-perangkat satu outlet biasanya di
        // balik satu IP (NAT); kunci per IP membuat polling pesanan terbuka/KDS dari beberapa perangkat saling
        // menghabiskan jatah. Rute tanpa perangkat (aktivasi) tetap dibatasi per IP.
        foreach ([10, 30, 60, 120, 600] as $perMenit) {
            RateLimiter::for("pos-{$perMenit}", static function (Request $permintaan) use ($perMenit): Limit {
                $perangkat = $permintaan->attributes->get(AutentikasiPerangkat::ATRIBUT);
                $rute = (string) $permintaan->route()?->getName();

                return Limit::perMinute($perMenit)->by($rute.'|'.($perangkat instanceof Perangkat ? 'perangkat:'.$perangkat->Id : 'ip:'.$permintaan->ip()));
            });
        }

        // F-17 Self-Order QR Meja (tanpa login): per rute, per meja (token), per IP. Tamu satu restoran biasanya di balik
        // satu IP wifi, jadi kunci per IP saja membuat meja-meja saling menghabiskan jatah polling status.
        foreach ([20, 60, 300] as $perMenit) {
            RateLimiter::for("pesan-sendiri-{$perMenit}", static function (Request $permintaan) use ($perMenit): Limit {
                $rute = $permintaan->route();
                $token = $rute?->parameter('tokenMeja');

                return Limit::perMinute($perMenit)->by($rute?->getName().'|'.(is_string($token) ? $token : '').'|'.$permintaan->ip());
            });
        }

        // P-05: email, CAPTCHA, dan penyimpanan objek memakai konfigurasi aktif dari Platform Pengelola.
        $this->app->make(PenerapKonfigurasiIntegrasi::class)->Terapkan();

        // P-09: pemberitahuan tiket dukungan (penangan di antrean, setelah commit).
        Event::listen(TiketDukunganDibuat::class, BeritahuTimTiketDukunganBaru::class);
        Event::listen(TiketDukunganDibalasPelapor::class, BeritahuPenanggungJawabBalasanPelapor::class);

        // P-11 BR-P11.1: /sehat (uptime monitor eksternal) ikut memeriksa alert, agar scheduler mati tetap terdeteksi.
        Event::listen(DiagnosingHealth::class, PeriksaOperasionalSaatCekSehat::class);

        if ($this->app->runningUnitTests()) {
            $this->loadMigrationsFrom(base_path('tests/Pendukung/Migrasi'));
        }
    }
}
