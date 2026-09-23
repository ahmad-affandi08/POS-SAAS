<?php

declare(strict_types=1);

namespace App\Http\Permintaan\Kelola;

final class UndangPenggunaPermintaan extends AksesAnggotaPermintaan
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['Email' => ['required', 'string', 'email:rfc', 'max:191'], ...parent::rules()];
    }
}
