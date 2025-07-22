<?php
declare(strict_types = 1);
/**
 *  FGSL Framework
 *  @author Flávio Gomes da Silva Lisboa <flavio.lisboa@fgsl.eti.br>
 *  @copyright FGSL 2020-2025
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
namespace Fgsl\Mvc\Controller;

use Fgsl\Db\TableGateway\AbstractTableGateway;
use Fgsl\Model\AbstractActiveRecord;
use Laminas\Db\ResultSet\ResultSet;
use Laminas\Db\Sql\Select;
use Laminas\Form\Form;
use Laminas\I18n\Translator\Resources;
use Laminas\I18n\Translator\Translator;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Mvc\I18n\Translator as MvcTranslator;
use Laminas\Paginator\Paginator;
use Laminas\Session\Container;
use Laminas\Validator\AbstractValidator;
use Laminas\View\Model\ViewModel;
use Laminas\View\Model\JsonModel;
use Laminas\Paginator\Adapter\LaminasDb\DbSelect;

abstract class AbstractCrudController extends AbstractActionController
{
    protected int $itemCountPerPage;

    protected string $modelClass;

    protected string $route;

    protected AbstractTableGateway $table;

    protected ?AbstractTableGateway $parentTable = null;

    protected string $tableClass;

    protected string $title;
    
    protected bool $activeRecordStrategy = false;
    
    protected string $pageArg = 'page';
    
    protected bool $jsonView = false;

    public function __construct(AbstractTableGateway $table, ?AbstractTableGateway $parentTable = null, $sessionManager = null)
    {
        $this->table = $table;
        $this->parentTable = $parentTable;
        if ($sessionManager != null) {
            $sessionManager->start();
        }
    }

    /**
     * The default action - show the home page
     */
    public function indexAction()
    {
        return $this->getListView($this->getPaginator());
    }

    protected function getPaginator($alternativeSelect = null): Paginator
    {
        $resultSet = new ResultSet();
        $resultSet->setArrayObjectPrototype($this->table->getModel(null));
        $pageAdapter = new DbSelect(is_null($alternativeSelect) ? $this->getSelect() : $alternativeSelect, $this->table->getSql(),$resultSet);
        $paginator = new Paginator($pageAdapter);
        $paginator->setCurrentPageNumber($this->params()
            ->fromRoute($this->pageArg, 1));
        $paginator->setItemCountPerPage($this->itemCountPerPage);
        return $paginator;
    }

    /**
     * Action to add/edit and change records
     */
    public function editAction()
    {
        $key = $this->params()->fromRoute('key', null);
        $model = $this->table->getModel($key);
        $form = $this->getForm(TRUE);
        $sessionContainer = new Container();
        $saved = false;
        if (isset($sessionContainer->model)) {
            $model->exchangeArray($sessionContainer->model->toArray());
            unset($sessionContainer->model);
            $form->setInputFilter($model->getInputFilter());
            $saved = true;
        }
        $form->bind($model);
        $this->initValidatorTranslator();
        if ($saved) {
            $form->isValid();
        }
        if ($this->jsonView){
            $view = new JsonModel();
        } else {
            $view = new ViewModel();
        }
        $view->setVariables([
            'form' => $form,
            'title' => $this->getEditTitle($key)
        ]);
        return $view;
    }

    /**
     * @param mixed $key
     */
    abstract function getEditTitle($key): string;

    abstract function getForm(bool $full = FALSE): Form;

    /**
     * Action to save a record
     */
    public function saveAction()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
            $form = $this->getForm();
            $model = $this->getObject($this->modelClass);
            $form->setInputFilter($model->getInputFilter());
            $post = $this->getPost();
            $form->setData($post);
            if (! $form->isValid()) {
                $sessionContainer = new Container();
                $sessionContainer->model = $post;
                return $this->redirect()->toRoute($this->route, [
                    'action' => 'edit',
                    'controller' => $this->getEvent()
                        ->getController()
                ]);
            }
            $model->populate($form->getData());
            if ($this->activeRecordStrategy){
                $model->save();
            } else {
                $this->table->save($model);
            }
            
        }
        return $this->redirect()->toRoute($this->route, [
            'controller' => $this->getControllerName()
        ]);
    }

    /**
     * Action to remove records
     */
    public function deleteAction()
    {
        $key = $this->params()->fromRoute('key', null);
        $this->table->delete($key);
        return $this->redirect()->toRoute($this->route, [
            'controller' => $this->getControllerName()
        ]);
    }
    
    /**
     * Action to pagination
     */
    public function pageAction()
    {
        return $this->redirect()->toRoute($this->route,[$this->pageArg => $this->params('key')]);
    }

    protected function initValidatorTranslator()
    {
        $translator = new Translator();
        $mvcTranslator = new MvcTranslator($translator);
        $mvcTranslator->addTranslationFilePattern(
            'phparray',
            Resources::getBasePath(),
            Resources::getPatternForValidator()
        );

        AbstractValidator::setDefaultTranslator($mvcTranslator);
    }

    protected function getObject($namespace): AbstractActiveRecord
    {
        return new $namespace(
            $this->table->getKeyName(),
            $this->table->getTable(),
            $this->table->getSql()->getAdapter()
        );
    }

    protected function getControllerName(): string
    {
        $tokens = explode('\\',str_replace('Controller','',get_called_class()));
        $controller = end($tokens);
        return lcfirst($controller);
    }
    
    protected function getPost(): \ArrayObject
    {
        return $this->getRequest()->getPost();
    }
    
    protected function getSelect(): Select
    {
        return $this->table->getSelect();
    }
    
    protected function getListView($paginator)
    {
        $controller = $this->getControllerName();
        
        $urlEdit = $this->url()->fromRoute($this->route, [
            'controller' => $controller,
            'action' => 'edit'
        ]);
        $urlHomepage = $this->url()->fromRoute('home');
        $urlDelete = $this->url()->fromRoute($this->route, [
            'controller' => $controller,
            'action' => 'delete'
        ]);
        
        $calledClass = get_called_class();
        $tokens = explode('\\',str_replace('Controller','',$calledClass));
        $controller = strtolower(end($tokens));
        if ($this->jsonView){
            $view = new JsonModel();
        } else {
            $view = new ViewModel();
        }
        $view->setVariables([
            'controller' => $controller,
            'paginator' => $paginator,
            'route' => $this->route,
            'urlEdit' => $urlEdit,
            'urlDelete' => $urlDelete,
            'urlHomepage' => $urlHomepage
        ]);
        return $view;
    }
}