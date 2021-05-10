<?php
namespace Fgsl\Test\Mvc;

use Fgsl\Mvc\Controller\AbstractCrudController;

class CrudController extends AbstractCrudController
{
    /**
     * @param mixed $key
     * @return string
     */
    function getEditTitle($key)
    {
        return 'Edit Title';
    }

    function getForm($full = FALSE)
    {
        return new CrudForm();
    }
}
