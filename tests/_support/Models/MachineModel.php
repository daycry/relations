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
class MachineModel extends Model
{
    use ModelTrait;

    protected $table              = 'machines';
    protected $primaryKey         = 'id';
    protected $returnType         = 'object';
    protected $useSoftDeletes     = false;
    protected $allowedFields      = ['type', 'serial', 'factory_id'];
    protected $useTimestamps      = true;
    protected $validationRules    = [];
    protected $validationMessages = [];
    protected $skipValidation     = false;
    protected $with               = ['factories'];
}
