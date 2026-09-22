<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use Database\Pabrik\PenggunaPabrik;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as KontrakDapatDiotorisasi;
use Illuminate\Contracts\Auth\Authenticatable as KontrakDapatDiautentikasi;
use Illuminate\Contracts\Auth\CanResetPassword as KontrakDapatResetKataSandi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;

/**
 * Akun pengguna tenant (PRD §15.3 `Pengguna`). Satu pengguna boleh menjadi anggota beberapa tenant
 * lewat `TenantPengguna` (BR-00.1), sehingga tabel ini tidak memakai MilikTenant.
 *
 * Akun tim internal Platform Pengelola memakai tabel terpisah `PenggunaPengelola` (§13.8).
 *
 * @property int $Id
 * @property string $Uuid
 * @property string $Nama
 * @property string $Email
 * @property string|null $NoHp
 * @property string $KataSandi
 */
final class Pengguna extends ModelDasar implements KontrakDapatDiautentikasi, KontrakDapatDiotorisasi, KontrakDapatResetKataSandi
{
    use Authenticatable;
    use Authorizable;
    use CanResetPassword;

    /** @use HasFactory<PenggunaPabrik> */
    use HasFactory;

    use Notifiable;

    protected $table = 'Pengguna';

    /** @var list<string> */
    protected $hidden = ['KataSandi', 'TokenIngat', 'Rahasia2fa'];

    public function getAuthPasswordName(): string
    {
        return 'KataSandi';
    }

    public function getRememberTokenName(): string
    {
        return 'TokenIngat';
    }

    public function getEmailForPasswordReset(): string
    {
        return $this->Email;
    }

    public function routeNotificationForMail(): string
    {
        return $this->Email;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'KataSandi' => 'hashed',
            'Rahasia2fa' => 'encrypted',
            'EmailDiverifikasiPada' => 'datetime',
        ];
    }
}
