<?php

declare(strict_types=1);

use Daycry\Relations\Traits\BaseTrait;
use Daycry\Relations\Contracts\RelatableInterface;
use Daycry\Relations\Traits\ModelTrait;
use CodeIgniter\Model;

#[AllowDynamicProperties]
class MetricsModel extends Model implements RelatableInterface
{
    use ModelTrait;
    protected $table = 'factories';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
}

final class MetricsTest extends \PHPUnit\Framework\TestCase
{
    public function testMetricsCountsIncreaseAndReset(): void
    {
        $metrics = MetricsModel::getRelationMetrics(true); // reset
        $this->assertSame(['calls' => 0, 'tables' => []], $metrics);

        // Enable metrics via runtime config tweak
        $config = config('Relations');
        $config->collectMetrics = true;

        $model = new MetricsModel();
        // Finder without relations (no $with) should not increase tables but may call addRelations zero times
        $model->findAll();

        // Force a relation load by setting with property dynamically
        $model->with('machines')->findAll();

        $after = MetricsModel::getRelationMetrics();
        $this->assertGreaterThanOrEqual(1, $after['calls']);
        $this->assertArrayHasKey('machines', $after['tables']);

        // Reset
        $reset = MetricsModel::getRelationMetrics(true);
        $this->assertSame(['calls' => 0, 'tables' => []], $reset);
    }
}
