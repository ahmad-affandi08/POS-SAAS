<?php

declare(strict_types=1);

namespace App\Http\Perantara;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Perantara Inertia: menentukan view root dan data yang dibagikan ke semua halaman (PRD §13.5).
 */
final class BagikanDataInertia extends Middleware
{
    protected $rootView = 'Aplikasi';

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'NamaAplikasi' => config('app.name'),
        ];
    }
}
