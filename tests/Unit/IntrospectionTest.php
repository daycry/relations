<?php

declare(strict_types=1);

use Tests\Support\Models\FactoryModel;
use Tests\Support\Entities\Factory;
use Daycry\Relations\Contracts\RelatableInterface;

final class IntrospectionTest extends \Tests\Support\TestCase
{
    public function testListRelationsFromModel(): void
    {
        $model = new FactoryModel();
        $rels = $model->listRelations();
        $this->assertIsArray($rels);
        $this->assertNotEmpty($rels);
        $this->assertContains('machines', $rels);
    }

    public function testRelationDetailsContainsType(): void
    {
        $model = new FactoryModel();
        $details = $model->relationDetails('machines');
        $this->assertArrayHasKey('machines', $details);
        $this->assertArrayHasKey('type', $details['machines']);
        $this->assertArrayHasKey('singleton', $details['machines']);
    }

    public function testEntityLoadMethod(): void
    {
        $model = new FactoryModel();
        $factory = $model->first();
        $entity = new Factory((array) $factory);
        $this->assertFalse(isset($entity->attributes['machines']));
        $entity->load('machines');
        $this->assertTrue(isset($entity->machines));
    }
}
