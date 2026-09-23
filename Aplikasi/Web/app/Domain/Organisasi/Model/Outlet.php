<?php

declare(strict_types=1);

namespace App\Domain\Organisasi\Model;

use App\Domain\Bersama\Model\ModelDasar;
use App\Domain\Bersama\Tenant\MilikTenant;
use App\Domain\Organisasi\Enum\StatusOrganisasi;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Outlet (PRD §15.3, F-02). F-00 membuat "Outlet Utama"; F-02 melengkapi kota, zona waktu, jam tutup buku, dan
 * profil pajak dasar. Outlet tidak pernah dihapus, hanya diarsipkan (dirujuk transaksi & laporan).
 *
 * `ProfilPajak` (disimpan F-02 & F-01, dipakai kalkulasi F-03/F-07): {"Pkp": bool, "Nitku": string|null,
 * "PungutPbjt": bool, "BiayaLayanan": {"Aktif": bool, "Persen": "5.00"}, "HargaTermasukPajak": bool}.
 * `TemplateSektor` + `IdTemplateSektorVersi` = template & versi terbit yang diterapkan F-01 (BR-P03.1); F-05/F-09/
 * F-10/F-14 membaca isi versi itu (alasan, stasiun dapur, laporan unggulan).
 * `KodeDikunciPada` diisi saat outlet mulai bertransaksi/punya perangkat; setelah itu kode tidak bisa diubah (BR-02.2).
 *
 * @property int $Id
 * @property string $Uuid
 * @property int $IdTenant
 * @property int $IdMerek
 * @property string $Kode
 * @property string $Nama
 * @property string|null $Alamat
 * @property string|null $KodeKota
 * @property string $ZonaWaktu
 * @property string|null $TemplateSektor
 * @property int|null $IdTemplateSektorVersi
 * @property Carbon|null $TemplateSektorDiterapkanPada
 * @property string $JamTutupBuku
 * @property array<string, mixed>|null $ProfilPajak
 * @property StatusOrganisasi $Status
 * @property Carbon|null $KodeDikunciPada
 * @property Carbon|null $DiarsipkanPada
 * @property-read Merek $Merek
 * @property-read Collection<int, Gudang> $Gudang
 */
final class Outlet extends ModelDasar
{
    use MilikTenant;

    protected $table = 'Outlet';

    /** @var array<string, mixed> */
    protected $attributes = [
        'Alamat' => null,
        'KodeKota' => null,
        'ZonaWaktu' => 'Asia/Jakarta',
        'TemplateSektor' => null,
        'IdTemplateSektorVersi' => null,
        'TemplateSektorDiterapkanPada' => null,
        'JamTutupBuku' => '04:00',
        'ProfilPajak' => null,
        'Status' => 'Aktif',
        'KodeDikunciPada' => null,
        'DiarsipkanPada' => null,
    ];

    /**
     * @return BelongsTo<Merek, $this>
     */
    public function Merek(): BelongsTo
    {
        return $this->belongsTo(Merek::class, 'IdMerek', 'Id');
    }

    /**
     * @return HasMany<Gudang, $this>
     */
    public function Gudang(): HasMany
    {
        return $this->hasMany(Gudang::class, 'IdOutlet', 'Id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ProfilPajak' => 'array',
            'Status' => StatusOrganisasi::class,
            'KodeDikunciPada' => 'datetime',
            'DiarsipkanPada' => 'datetime',
            'TemplateSektorDiterapkanPada' => 'datetime',
        ];
    }
}
