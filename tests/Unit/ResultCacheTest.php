<?php

declare(strict_types=1);

use Tests\Support\Models\FactoryModel;

final class ResultCacheTest extends \Tests\Support\TestCase
{
    public function testRelationResultCacheSkipsSecondQuery(): void
    {
        $config = config('Relations');
        $config->collectMetrics = true;
        $config->cacheRelationResults = true;

        // Reset metrics & cache
    FactoryModel::getRelationMetrics(true);
    FactoryModel::clearRelationResultCache();

        $model = new FactoryModel();
        $model->with('machines')->findAll();
    $first = FactoryModel::getRelationMetrics();
        $callsAfterFirst = $first['calls'];

        $model->with('machines')->findAll();
    $second = FactoryModel::getRelationMetrics();

        // Expect no increment in calls for the same relation (strict equality) because cache served it
        $this->assertSame($callsAfterFirst, $second['calls']);
    }
}
