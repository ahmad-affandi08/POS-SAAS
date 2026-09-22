<?php

declare(strict_types=1);

namespace App\Domain\Bersama\Tenant;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope: setiap query model tenant otomatis dibatasi ke tenant aktif.
 *
 * @template TModel of Model
 *
 * @implements Scope<TModel>
 */
final class LingkupTenant implements Scope
{
    /**
     * @param  Builder<covariant TModel>  $builder
     * @param  TModel  $model
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where($model->qualifyColumn('IdTenant'), app(KonteksTenant::class)->Wajib());
    }
}
