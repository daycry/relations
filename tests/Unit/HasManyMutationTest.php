<?php

declare(strict_types=1);

use Tests\Support\Models\FactoryModel;
use Tests\Support\Models\MachineModel;
use Tests\Support\Entities\Factory;

final class HasManyMutationTest extends \Tests\Support\TestCase
{
    public function testSetHasManyReplacesChildren(): void
    {
        $factoryModel = new FactoryModel();
        $machineModel = new MachineModel();
        $factory = $factoryModel->first();
        $entity = new Factory((array) $factory);
        // Obtener máquinas actuales
        $original = $entity->machines;
        $this->assertNotEmpty($original);

        // Crear nueva máquina sin asignar (factory_id null)
        $newId = $machineModel->insert([
            'type' => 'temp',
            'serial' => 'SERIAL-X-' . uniqid(),
            'factory_id' => null,
        ], true);
        $this->assertIsInt($newId);

        // Reemplazar set solo con la nueva
        $entity->setMachines([$newId]);
        $updated = $entity->relations('machines', true);
        $this->assertCount(1, $updated);
        $this->assertSame($newId, $updated[0]);
    }

    public function testAddHasManyOnlyAdoptsUnassigned(): void
    {
        $factoryModel = new FactoryModel();
        $machineModel = new MachineModel();
        $factory = $factoryModel->first();
        $entity = new Factory((array) $factory);
        $before = $entity->relations('machines', true);

        $newId = $machineModel->insert([
            'type' => 'temp2',
            'serial' => 'SERIAL-Y-' . uniqid(),
            'factory_id' => null,
        ], true);

        $entity->addMachines([$newId]);
        $after = $entity->relations('machines', true);
        $this->assertContains($newId, $after);
        $this->assertCount(count($before) + 1, $after);
    }

    public function testRemoveHasManyNullsForeignKey(): void
    {
        $factoryModel = new FactoryModel();
        $factory = $factoryModel->first();
        $entity = new Factory((array) $factory);
        $current = $entity->relations('machines', true);
        $this->assertNotEmpty($current);
        $remove = [$current[0]];
        $entity->removeMachines($remove);
        $after = $entity->relations('machines', true);
        $this->assertFalse(in_array($remove[0], $after, true));
    }
}
