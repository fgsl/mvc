<?php
namespace Fgsl\Test\Mvc;

use Fgsl\Mvc\Controller\AbstractCrudController;
use Laminas\Form\Form;

class CrudController extends AbstractCrudController
{
    /**
     * @param mixed $key
     * @return string
     */
    function getEditTitle($key): string
    {
        return 'Edit Title';
    }

    function getForm($full = FALSE): Form
    {
        return new CrudForm();
    }
}
