<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Relations.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\Support;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use Daycry\Schemas\Config\Schemas as SchemasConfig;
use Daycry\Schemas\Schemas;

/**
 * @internal
 */
abstract class TestCase extends CIUnitTestCase
{
    /**
     * Instance of the library.
     */
    protected $schemas;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure and inject the Schemas service
        $config                    = new SchemasConfig();
        $config->silent            = false;
        $config->ignoredNamespaces = [];

        $schemas = new Schemas($config);
        Services::injectMock('schemas', $schemas);
    }
}
