<?php

declare(strict_types=1);

namespace Tests\Model;

use Tests\Support\DatabaseTestCase;
use CodeIgniter\Model;
use Daycry\Relations\Contracts\RelatableInterface;
use Daycry\Relations\Traits\ModelTrait;
use AllowDynamicProperties;

#[AllowDynamicProperties]
class ServicerOverrideModel extends Model implements RelatableInterface
{
    use ModelTrait;

    protected $table      = 'servicers';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $allowedFields = ['company'];

    protected array $relationTypeOverrides = [
        'lawyers' => 'hasOne',
    ];

    protected $with = ['lawyers'];
}

final class TypeOverrideTest extends DatabaseTestCase
{
    public function testHasManyPromotedToHasOne(): void
    {
        $model = new ServicerOverrideModel();
    $servicer = $model->find(1); // relies on seeder creating ID 1
    $this->assertNotNull($servicer, 'Expected seeded servicer with ID 1');
        $this->assertTrue(property_exists($servicer, 'lawyer'));
        $this->assertFalse(property_exists($servicer, 'lawyers'));
    $this->assertTrue(is_object($servicer->lawyer) || is_array($servicer->lawyer), 'Expected lawyer relation as object or array');
    }
}
