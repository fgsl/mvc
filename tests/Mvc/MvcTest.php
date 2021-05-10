<?php
namespace Fgsl\Test\Mvc;

use Fgsl\Mvc\Controller\AbstractCrudController;
use PHPUnit\Framework\TestCase;

class MvcTest extends TestCase
{
    public function testMvc() 
    {
        $controller = new CrudController(null,null);    
        
        $this->assertIsObject($controller->getForm());        
    }
}
