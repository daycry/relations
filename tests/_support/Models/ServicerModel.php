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

namespace Tests\Support\Models;

use AllowDynamicProperties;
use CodeIgniter\Model;
use Daycry\Relations\Traits\ModelTrait;

#[AllowDynamicProperties]
class ServicerModel extends Model
{
    use ModelTrait;

    protected $table              = 'servicers';
    protected $primaryKey         = 'id';
    protected $returnType         = 'object';
    protected $useSoftDeletes     = true;
    protected $allowedFields      = ['company'];
    protected $useTimestamps      = true;
    protected $validationRules    = [];
    protected $validationMessages = [];
    protected $skipValidation     = false;
    protected $with               = ['lawyers'];
}
