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

namespace Daycry\Relations\Language\en;

return [
    'noSchemas'        => 'Unable to load Schemas library!',
    'invalidWithout'   => '"Without" parameter must be a table name or array of names',
    'unknownTable'     => 'Table not present in schema: {0}',
    'unknownRelation'  => 'Table {0} is not known to be related to {1}',
    'missingPivots'    => 'Table {0} does not indicate a pivot route to {1}',
    'missingProperty'  => 'Class {0} must have the {1} property to use relations',
    'notRelatable'     => 'Class {0} must implement RelatableInterface to use relations',
    'invalidOperation' => 'Operation {0} not valid on {1}',
];
