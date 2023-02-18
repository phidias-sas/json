<?php

namespace Phidias\JsonDb;

use Phidias\Json\Sql\Vm as SqlVm;
use Exception;

class Dataset
{
    private $sources;
    private $maxLimit;
    private $vm;

    public function __construct()
    {
        $this->sources = [];
        $this->maxLimit = 64000;
        $this->vm = new SqlVm();
    }

    public function addSource($sourceName, $sourceObject, $isDefault = false)
    {
        if (!is_a($sourceObject, 'Phidias\JsonDb\Source')) {
            throw new Exception("Source must be of type Phidias\JsonDb\Source");
        }

        $this->sources[$sourceName] = $sourceObject;
        if ($isDefault) {
            $this->sources['default'] = $this->sources[$sourceName];
        }

        return $this;
    }

    public function defineOperator($operatorName, $callable)
    {
        return $this->vm->defineOperator($operatorName, $callable);
    }

    public function query($query, $matchableValues = null)
    {
        $retval = [];
        $query = Select::factory($query);

        if (!isset($this->sources[$query->from->db])) {
            throw new Exception("Source '{$query->from->db}' not found in dataset");
        }

        $source = $this->sources[$query->from->db];
        $table = $source->getTable($query->from->table);

        $table->setVm($this->vm);

        // Establecer propiedades a seleccionar e identificar relaciones
        $useAllProperties = false;
        $properties = [];
        $relations = [];

        foreach ($query->properties as $property) {
            if (is_object($property)) {
                $propName = array_keys(get_object_vars($property))[0];
                $propSource = $property->$propName;

                if (is_object($propSource)) {
                    if (!isset($propSource->on)) {
                        throw new Exception("No 'on' specified in nested source '$propName'");
                    }

                    $relations[] = (object)[
                        "propName" => $propName,
                        "query" => $propSource,
                        "on" => $propSource->on,
                        "hash" => [],  // This query's records hashed according to the subquery columns
                        /* e.g.
                        on: {
                            personId: x,
                            groupId: x
                        }
                        hash['p1:g1:'] => the record that has personId: p1, groupId: g1
                        */

                        "matchableValues" => []
                        /*
                        matchableValues[personId] = [1,2,3,4,....]
                        matchableValues[groupId] = [1,2,3,4,....]
                        matchableValues['$meta.authorId'] = [...]
                        */
                    ];

                    // Make sure all "on"d attributes are present
                    foreach ($propSource->on as $localColumn) {
                        $table->attribute($localColumn);
                    }
                } else {
                    $table->attribute($propName, $propSource);
                    $properties[] = $propName;
                }
            } else {
                $table->attribute($property);
                $properties[] = $property;

                if ($property == "*") {
                    $useAllProperties = true;
                }
            }
        }

        // Raw SQL
        if ($query->sql) {
            $table->sql($query->sql->query, $query->sql->params);
        }

        // Establecer condiciones de "match"
        foreach ($query->match as $keyName => $keyValue) {

            // si $keyValue es un objeto "query", hacer un match interno :)
            if (isset($keyValue->from)) {

                if (is_array($keyValue->properties) && count($keyValue->properties) == 1) {
                    $targetProp = $keyValue->properties[0];
                } else if (is_string($keyValue->properties)) {
                    $targetProp = $keyValue->properties;
                } else {
                    throw new Exception("En un match anidado, 'properties' debe ser un STRING o tener un unico elemento");
                }

                $targetValues = [];
                $matchTargets = $this->query($keyValue);
                foreach ($matchTargets as $matchingRecord) {
                    if (isset($matchingRecord->$targetProp)) {
                        $targetValues[$matchingRecord->$targetProp] = $matchingRecord->$targetProp;
                    }
                }

                $keyValue = array_values($targetValues);
            }

            $table->match($keyName, $keyValue);
        }

        // Establecer condicionales ("where")
        if ($query->where) {
            $table->where($query->where);
        }

        // Establecer condicion de agrupador ("having")
        if ($query->having) {
            $table->having($query->having);
        }

        // Establecer agrupador ("groupBy")
        if ($query->groupBy) {
            $table->groupBy($query->groupBy);
        }

        // Establecer busqueda ("search")
        if ($query->search) {
            $table->search($query->search);
        }

        // Limite
        $limit = $this->maxLimit;
        if (isset($query->limit)) {
            $limit = max(0, min($limit, $query->limit));
        }
        $table->limit($limit);

        // Página
        if (isset($query->page)) {
            $page = max(1, $query->page);
            $table->page($page);
        }

        // Orden
        if (isset($query->order)) {
            $table->order($query->order);
        }

        // Si este es un sub-query, filtrar segun los datos de la condicion de join ("on")
        if ($matchableValues) {
            foreach ($matchableValues as $columnName => $columnValues) {
                $table->attribute($columnName);
                $table->match($columnName, $columnValues);
                $properties[] = $columnName;
            }
        }

        // Fetch all records
        foreach ($table->fetch() as $record) {
            if ($useAllProperties) {
                $retvalItem = $record;
            } else {
                $retvalItem = new \stdClass;
                $retvalItem->id = isset($record->id) ? $record->id : null; // plop id always

                foreach ($properties as $propName) {
                    $retvalItem->$propName = isset($record->$propName) ? $record->$propName : null;
                }
            }

            // Populate relation hashes
            foreach ($relations as $relationData) {
                // Inicializar la propiedad relacionada en blanco
                $retvalItem->{$relationData->propName} = $relationData->query->isSingle ? null : [];

                foreach ($relationData->on as $foreignPropName => $localPropName) {
                    if (!Utils::getProperty($record, $localPropName)) {
                        continue 2;
                    }
                }

                $hashKey = '';
                foreach ($relationData->on as $foreignPropName => $localPropName) {
                    $hashValue = Utils::getProperty($record, $localPropName);
                    $hashKey .= $hashValue . ':';

                    // Build match condition for subquery
                    if (!isset($relationData->matchableValues[$foreignPropName])) {
                        $relationData->matchableValues[$foreignPropName] = [];
                    }
                    $relationData->matchableValues[$foreignPropName][$hashValue] = $hashValue;
                }

                if (!isset($relationData->hash[$hashKey])) {
                    $relationData->hash[$hashKey] = [];
                }

                $relationData->hash[$hashKey][] = $retvalItem;
            }

            $retval[] = $retvalItem;
        }

        // Resolve each relation
        foreach ($relations as $relationData) {
            // Fetch all related records
            if (count($relationData->hash) && count($relationData->matchableValues)) {
                $relatedRecords = $this->query($relationData->query, $relationData->matchableValues);
            } else {
                $relatedRecords = [];
            }

            // Merge related records into result
            foreach ($relatedRecords as $relRecord) {
                $hashKey = '';
                foreach ($relationData->on as $foreignPropName => $localPropName) {
                    if (!isset($relRecord->$foreignPropName)) {
                        continue 2;
                    }
                    $hashKey .= $relRecord->$foreignPropName . ':';
                }

                $parentRecords = isset($relationData->hash[$hashKey]) ? $relationData->hash[$hashKey] : [];
                foreach ($parentRecords as $parentRecord) {
                    if ($relationData->query->isSingle) {
                        $parentRecord->{$relationData->propName} = $relRecord;
                    } else {
                        if (!isset($parentRecord->{$relationData->propName}) || !is_array($parentRecord->{$relationData->propName})) {
                            $parentRecord->{$relationData->propName} = [];
                        }
                        $parentRecord->{$relationData->propName}[] = $relRecord;
                    }
                }
            }
        }

        return $retval;
    }
}
