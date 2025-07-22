<?php
namespace Fgsl\Test\Mvc;

use PHPUnit\Framework\TestCase;

class MvcTest extends TestCase
{
    /**
     * @covers CrudController
     */
    public function testMvc()
    {
        $controller = new CrudController($this->createMock(\Fgsl\Db\TableGateway\AbstractTableGateway::class),null);
        
        $this->assertIsObject($controller->getForm());
    }
}
