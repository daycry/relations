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

use Tests\Support\Models\FactoryModel;
use Tests\Support\Models\MachineModel;
use Tests\Support\TestCase;

/**
 * @internal
 */
final class ModelTest extends TestCase
{
    public function testWithString()
    {
        $model = new FactoryModel();
        $model->with('machines');

        $result = $this->getPrivateProperty($model, 'tmpWith');

        $this->assertSame(['machines'], $result);
    }

    public function testWithArray()
    {
        $model = new FactoryModel();
        $model->with(['machines', 'workers']);

        $result = $this->getPrivateProperty($model, 'tmpWith');

        $this->assertSame(['machines', 'workers'], $result);
    }

    public function testWithMerges()
    {
        $model = new MachineModel();
        $model->with('servicers');

        $result = $this->getPrivateProperty($model, 'tmpWith');

        $this->assertSame(['factories', 'servicers'], $result);
    }

    public function testWithOverwrites()
    {
        $model = new MachineModel();
        $model->with('servicers', true);

        $result = $this->getPrivateProperty($model, 'tmpWith');

        $this->assertSame(['servicers'], $result);
    }

    public function testWithRepeats()
    {
        $model = new FactoryModel();
        $model->with('machines')->with('workers');

        $result = $this->getPrivateProperty($model, 'tmpWith');

        $this->assertSame(['workers'], $result);
    }

    public function testWithFalse()
    {
        $model = new MachineModel();
        $model->with(false);

        $result = $this->getPrivateProperty($model, 'tmpWith');

        $this->assertEmpty($result);
    }

    public function testWithoutString()
    {
        $model = new FactoryModel();
        $model->without('machines');

        $result = $this->getPrivateProperty($model, 'tmpWithout');

        $this->assertSame(['machines'], $result);
    }

    public function testWithoutArray()
    {
        $model = new FactoryModel();
        $model->without(['machines', 'workers']);

        $result = $this->getPrivateProperty($model, 'tmpWithout');

        $this->assertSame(['machines', 'workers'], $result);
    }
}
