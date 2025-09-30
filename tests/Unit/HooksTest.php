<?php

declare(strict_types=1);

use CodeIgniter\Model;
use Daycry\Relations\Contracts\RelatableInterface;
use Daycry\Relations\Traits\ModelTrait;
use Daycry\Relations\Traits\EntityTrait;
use CodeIgniter\Entity\Entity;

#[\AllowDynamicProperties]
class HookModel extends Model implements RelatableInterface
{
    use ModelTrait;
    protected $table = 'factories';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $with = ['machines'];

    public array $afterCalled = [];

    protected function afterRelations(array &$rows, array $loadedTables): void
    {
        // Tag rows for assertion
        foreach ($rows as $k => $row) {
            if (is_object($row)) {
                $row->__tag = 'afterRelations';
            } else {
                $row['__tag'] = 'afterRelations';
            }
            $rows[$k] = $row;
        }
        $this->afterCalled = $loadedTables;
    }
}

class HookEntity extends Entity implements RelatableInterface
{
    use EntityTrait;
    protected $table = 'factories';
    protected $primaryKey = 'id';

    public array $entityHooks = [];

    protected function afterEntityRelations(string $table, mixed &$result, bool $keysOnly): void
    {
        $this->entityHooks[] = $table;
        // Mark first element / object
        if ($result !== null) {
            if (is_array($result)) {
                if (isset($result[0]) && is_object($result[0])) { $result[0]->__etag = 'afterEntity'; }
            } elseif (is_object($result)) {
                $result->__etag = 'afterEntity';
            }
        }
    }
}

final class HooksTest extends \Tests\Support\TestCase
{
    public function testAfterRelationsHook(): void
    {
        $model = new HookModel();
        $rows = $model->findAll();
        $this->assertNotEmpty($rows);
        $first = reset($rows);
        $this->assertTrue(isset($first->__tag));
        $this->assertContains('machines', $model->afterCalled);
    }

    public function testAfterEntityRelationsHook(): void
    {
        $model = new HookModel();
        $factory = $model->first();
        // Convert factory (stdClass or array) into array for entity hydration
        if (is_object($factory)) {
            $factoryArray = get_object_vars($factory);
        } else {
            $factoryArray = $factory;
        }
        $entity = new HookEntity($factoryArray);
    // Trigger lazy load twice: first full, then keysOnly to ensure hook called for same table only once logically
    // Explicitly call relations to trigger hook (avoids potential attribute shortcut)
    $machines = $entity->relations('machines');
    $this->assertNotEmpty($machines);
    $keys = $entity->relations('machines', true);
    $this->assertNotEmpty($keys);
    $this->assertContains('machines', $entity->entityHooks);
    // Mark should exist on first machine object
    $first = is_array($machines) ? $machines[0] : $machines;
    $this->assertTrue(isset($first->__etag));
    }
}
