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

use Tests\Support\DatabaseTestCase;
use Tests\Support\Entities\Factory;

/**
 * @internal
 */
final class ManyMethodsTest extends DatabaseTestCase
{
    protected array $row        = [];
    protected ?Factory $factory = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->row     = $this->db->table('factories')->where('id', 1)->get()->getRowArray();
        $this->factory = new Factory($this->row);
    }

    public function testHasAnySuccess()
    {
        $method = $this->getPrivateMethodInvoker($this->factory, '_has');

        $this->assertTrue($method('workers'));
    }

    public function testHasAnyFail()
    {
        $row     = $this->db->table('factories')->where('id', 4)->get()->getRowArray();
        $factory = new Factory($row);
        $method  = $this->getPrivateMethodInvoker($factory, '_has');

        $this->assertFalse($method('workers'));
    }

    public function testHasEverySuccess()
    {
        $method = $this->getPrivateMethodInvoker($this->factory, '_has');

        $this->assertTrue($method('workers', [2, 4]));
    }

    public function testHasEveryFail()
    {
        $method = $this->getPrivateMethodInvoker($this->factory, '_has');

        $this->assertFalse($method('workers', [4, 5]));
    }

    public function testAdd()
    {
        $method = $this->getPrivateMethodInvoker($this->factory, '_add');
        $result = $method('workers', [9]);

        $this->assertTrue($result);

        $builder = $this->db->table('factories_workers');

        $this->assertSame(5, $builder->where('factory_id', 1)->countAllResults());
    }

    public function testAddMany()
    {
        $method = $this->getPrivateMethodInvoker($this->factory, '_add');
        $result = $method('workers', [5, 6, 7, 8, 9]);

        $this->assertTrue($result);

        $builder = $this->db->table('factories_workers');

        $this->assertSame(9, $builder->where('factory_id', 1)->countAllResults());
    }

    public function testAddEmptyFails()
    {
        $method = $this->getPrivateMethodInvoker($this->factory, '_add');
        $result = $method('workers', []);

        $this->assertFalse($result);

        $builder = $this->db->table('factories_workers');

        $this->assertSame(4, $builder->where('factory_id', 1)->countAllResults());
    }

    public function testRemove()
    {
        $method = $this->getPrivateMethodInvoker($this->factory, '_remove');
        $result = $method('workers', [2, 3]);

        $this->assertTrue($result);

        $builder = $this->db->table('factories_workers');

        $this->assertSame(2, $builder->where('factory_id', 1)->countAllResults());
    }

    public function testRemoveEmptyFails()
    {
        $method = $this->getPrivateMethodInvoker($this->factory, '_remove');
        $result = $method('workers', []);

        $this->assertFalse($result);

        $builder = $this->db->table('factories_workers');

        $this->assertSame(4, $builder->where('factory_id', 1)->countAllResults());
    }

    public function testSet()
    {
        $method = $this->getPrivateMethodInvoker($this->factory, '_set');
        $result = $method('workers', [9]);

        $this->assertTrue($result);

        $builder = $this->db->table('factories_workers');

        $this->assertSame(1, $builder->where('factory_id', 1)->countAllResults());
    }

    public function testSetMany()
    {
        $method = $this->getPrivateMethodInvoker($this->factory, '_set');
        $result = $method('workers', [1, 3, 5, 7, 9]);

        $this->assertTrue($result);

        $builder = $this->db->table('factories_workers');

        $this->assertSame(5, $builder->where('factory_id', 1)->countAllResults());
    }

    public function testSetEmpty()
    {
        $method = $this->getPrivateMethodInvoker($this->factory, '_set');
        $result = $method('workers', []);

        $this->assertTrue($result);

        $builder = $this->db->table('factories_workers');

        $this->assertSame(0, $builder->where('factory_id', 1)->countAllResults());
    }
}
