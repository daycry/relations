<?php

declare(strict_types=1);

namespace Daycry\Relations\Config;

use CodeIgniter\Config\BaseService;

class Registrar
{
    /**
     * Returns an array of CLI commands to register.
     * @return array<string,string>
     */
    public static function commands(): array
    {
        return [
            'relations:inspect' => \Daycry\Relations\Commands\RelationsInspect::class,
        ];
    }
}
