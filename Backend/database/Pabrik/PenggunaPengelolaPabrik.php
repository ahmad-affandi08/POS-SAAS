<?php

declare(strict_types=1);

namespace Database\Pabrik;

use App\Domain\Pengelola\TimInternal\Aksi\SiapkanPeranBawaan;
use App\Domain\Pengelola\TimInternal\Enum\PeranPengelolaBawaan;
use App\Domain\Pengelola\TimInternal\Model\PenggunaPengelola;
use App\Domain\Pengelola\TimInternal\Model\PeranPengelola;
use Illuminate\Database\Eloquent\Factories\Factory;
use PragmaRX\Google2FA\Google2FA;

/**
 * @extends Factory<PenggunaPengelola>
 */
final class PenggunaPengelolaPabrik extends Factory
{
    protected $model = PenggunaPengelola::class;

    public const KATA_SANDI = 'kata-sandi-uji-123';

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'Nama' => fake('id_ID')->name(),
            'Email' => fake()->unique()->safeEmail(),
            'KataSandi' => self::KATA_SANDI,
            'Aktif' => true,
        ];
    }

    public function DenganDuaFaktor(?string $rahasia = null): self
    {
        return $this->state(fn (): array => [
            'Rahasia2fa' => $rahasia ?? (new Google2FA)->generateSecretKey(32),
            'KodePemulihan2fa' => ['AAAAA-BBBBB', 'CCCCC-DDDDD'],
            'DuaFaktorAktifPada' => now(),
        ]);
    }

    public function Nonaktif(): self
    {
        return $this->state(fn (): array => ['Aktif' => false, 'DinonaktifkanPada' => now()]);
    }

    public function DenganPeran(PeranPengelolaBawaan ...$daftarPeran): self
    {
        return $this->afterCreating(function (PenggunaPengelola $pengguna) use ($daftarPeran): void {
            app(SiapkanPeranBawaan::class)->Jalankan();

            $idPeran = PeranPengelola::query()
                ->whereIn('Kode', array_map(fn (PeranPengelolaBawaan $peran) => $peran->value, $daftarPeran))
                ->pluck('Id')
                ->all();

            $pengguna->Peran()->attach($idPeran);
            $pengguna->LupakanIzin();
        });
    }
}
