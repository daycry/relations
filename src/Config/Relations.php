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

namespace Daycry\Relations\Config;

use CodeIgniter\Config\BaseConfig;

class Relations extends BaseConfig
{
    // Whether to continue instead of throwing exceptions
    public bool $silent = true;

    // Whether related items can load their own relations
    public bool $allowNesting = true;

    // Return type to fall back to if no model is available
    public string $defaultReturnType = 'object';

    // Whether to collect in-process relation metrics (calls & table counts)
    public bool $collectMetrics = false;

    /**
     * Enable simple in-process caching of relation result sets (per request lifecycle).
     */
    public bool $cacheRelationResults = false;

    /**
     * Strategy for belongsTo loading: 'join' (default) or 'in'. (Reserved for future optimization.)
     */
    public string $belongsToStrategy = 'join';
}
