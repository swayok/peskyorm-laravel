<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\Traits;

use Illuminate\Routing\Route;
use PeskyORM\ORM\RecordInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * @psalm-require-implements \Illuminate\Routing\Controller
 */
trait InjectsDbRecords
{
    
    protected function injectOnlyActiveObjects(): bool
    {
        return true;
    }
    
    protected function injectOnlyNotSoftDeletedObjects(): bool
    {
        return true;
    }
    
    public function callAction($method, $parameters): Response
    {
        $this->readDbObjectForInjection($parameters);
        /** @noinspection PhpMultipleClassDeclarationsInspection */
        return parent::callAction($method, $parameters);
    }
    
    /**
     * @param $parameters
     */
    protected function readDbObjectForInjection($parameters): void
    {
        $request = request();
        $route = $request->route();
        $object = null;
        foreach ($parameters as $value) {
            if ($value instanceof RecordInterface) {
                // get only last object in params
                $object = $value;
            }
        }
        if ($object) {
            $id = $route->parameter('id', false);
            if ($id === false && $request->method() !== 'GET') {
                $id = $request->get('id', false);
            }
            if (empty($id)) {
                $this->sendRecordNotFoundResponseForInjectedRecord();
            }
            $conditions = [
                $object::getTable()
                    ->getPkColumnName() => $id,
            ];
            $this->addConditionsForDbObjectInjection($route, $object, $conditions);
            $this->addIsActiveAndIsDeletedConditionsForDbObjectInjection($route, $object, $conditions);
            $this->addParentIdsConditionsForDbObjectInjection($route, $object, $conditions);
            $object->fetch($conditions, $this->getColumnsListForDbObjectInjection($object));
            if (!$object->existsInDb()) {
                $this->sendRecordNotFoundResponseForInjectedRecord();
            }
        }
    }
    
    /** @noinspection PhpUnusedParameterInspection */
    protected function getColumnsListForDbObjectInjection(RecordInterface $object): array
    {
        return ['*']; //< '*' here will skip heavy columns. To read all columns use empty array
    }
    
    /**
     * Abort with HTTP code 404
     */
    protected function sendRecordNotFoundResponseForInjectedRecord(): void
    {
        abort(404, 'Record not found in DB.');
    }
    
    /**
     * @param Route $route
     * @param RecordInterface $object
     * @param array $conditions
     */
    protected function addConditionsForDbObjectInjection(Route $route, RecordInterface $object, array &$conditions): void
    {
    }
    
    /**
     * @param Route $route
     * @param RecordInterface $object
     * @param array $conditions
     */
    protected function addIsActiveAndIsDeletedConditionsForDbObjectInjection(Route $route, RecordInterface $object, array &$conditions): void
    {
        if (
            $this->injectOnlyActiveObjects()
            && $object::getTable()->getTableStructure()->hasColumn('is_active')
        ) {
            $conditions['is_active'] = (bool)$route->parameter('is_active', true);
        }
        if (
            $this->injectOnlyNotSoftDeletedObjects()
            && $object::getTable()->getTableStructure()->hasColumn('is_deleted')
        ) {
            $conditions['is_deleted'] = (bool)$route->parameter('is_deleted', false);
        }
    }
    
    /**
     * @param Route $route
     * @param RecordInterface $object
     * @param array $conditions
     */
    protected function addParentIdsConditionsForDbObjectInjection(Route $route, RecordInterface $object, array &$conditions): void
    {
        foreach ($route->parameterNames() as $name) {
            if ($object::hasColumn($name)) {
                $conditions[$name] = $route->parameter($name);
            }
        }
    }
}
