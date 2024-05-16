<?php

declare(strict_types=1);

namespace PeskyORMLaravel\Db\Traits;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Request;
use PeskyORM\ORM\Record\RecordInterface;
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
        return parent::callAction($method, $parameters);
    }

    /**
     * @param $parameters
     */
    protected function readDbObjectForInjection($parameters): void
    {
        $request = Request::instance();
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
                $object->getPrimaryKeyColumnName() => $id,
            ];
            $this->addConditionsForDbObjectInjection(
                $route,
                $object,
                $conditions
            );
            $this->addIsActiveAndIsDeletedConditionsForDbObjectInjection(
                $route,
                $object,
                $conditions
            );
            $this->addParentIdsConditionsForDbObjectInjection(
                $route,
                $object,
                $conditions
            );
            $object->fetch(
                $conditions,
                $this->getColumnsListForDbObjectInjection($object)
            );
            if (!$object->existsInDb()) {
                $this->sendRecordNotFoundResponseForInjectedRecord();
            }
        }
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function getColumnsListForDbObjectInjection(RecordInterface $object): array
    {
        // '*' here will skip heavy columns. To read all columns use empty array
        return ['*'];
    }

    /**
     * Abort with HTTP code 404
     */
    protected function sendRecordNotFoundResponseForInjectedRecord(): void
    {
        abort(404, 'Record not found in DB.');
    }

    protected function addConditionsForDbObjectInjection(
        Route $route,
        RecordInterface $object,
        array &$conditions
    ): void {
    }

    protected function addIsActiveAndIsDeletedConditionsForDbObjectInjection(
        Route $route,
        RecordInterface $object,
        array &$conditions
    ): void {
        if (
            $this->injectOnlyActiveObjects()
            && $object->hasColumn('is_active')
        ) {
            $conditions['is_active'] = (bool)$route->parameter('is_active', true);
        }
        if (
            $this->injectOnlyNotSoftDeletedObjects()
            && $object->hasColumn('is_deleted')
        ) {
            $conditions['is_deleted'] = (bool)$route->parameter('is_deleted', false);
        }
    }

    protected function addParentIdsConditionsForDbObjectInjection(
        Route $route,
        RecordInterface $object,
        array &$conditions
    ): void {
        $tableStructure = $object->getTable()->getTableStructure();
        foreach ($route->parameterNames() as $name) {
            if ($tableStructure->hasColumn($name)) {
                $conditions[$name] = $route->parameter($name);
            }
        }
    }
}
