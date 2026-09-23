<?php

declare(strict_types=1);

namespace Database\Pabrik;

use App\Domain\Organisasi\Model\Pengguna;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pengguna>
 */
final class PenggunaPabrik extends Factory
{
    protected $model = Pengguna::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'Nama' => fake('id_ID')->name(),
            'Email' => fake()->unique()->safeEmail(),
            'NoHp' => '08'.fake()->unique()->numerify('##########'),
            'EmailDiverifikasiPada' => now(),
            'KataSandi' => 'kata-sandi-uji',
        ];
    }
}
