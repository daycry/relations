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

namespace Tests\Support\Entities;

use CodeIgniter\Entity\Entity;
use Daycry\Relations\Traits\EntityTrait;

class Factory extends Entity
{
    use EntityTrait;

    protected $table      = 'factories';
    protected $primaryKey = 'id';
    protected $dates      = ['created_at', 'updated_at', 'deleted_at'];
}
