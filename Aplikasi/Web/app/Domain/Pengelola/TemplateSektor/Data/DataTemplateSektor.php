<?php

declare(strict_types=1);

namespace App\Domain\Pengelola\TemplateSektor\Data;

final readonly class DataTemplateSektor
{
    public function __construct(
        public string $kode,
        public string $nama,
        public ?string $keterangan,
        public ?string $kodeTemplateDasar,
    ) {}
}
