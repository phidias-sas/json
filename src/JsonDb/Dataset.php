<?php

namespace Phidias\JsonDb;

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

    public function query($query, $joinData = null)
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

                    // on: {"foreignColumn": "localColumn"}
                    $foreignColumn = array_keys(get_object_vars($propSource->on))[0];
                    $localColumn = $propSource->on->$foreignColumn;

                    $relations[] = (object)[
                        "propName" => $propName,
                        "query" => $propSource,
                        "foreign" => $foreignColumn,
                        "local" => $localColumn,
                        "hash" => []
                    ];

                    // Asegurarse de que el atributo a comparar venga en los registros
                    $table->attribute($localColumn);
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
        if ($joinData) {
            $table->match($joinData->keyName, $joinData->keyValue);

            $table->attribute($joinData->keyName);
            $properties[] = $joinData->keyName;
        }

        // Fetch all records and populate relation condition data
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

            foreach ($relations as $relationData) {
                // Inicializar la propiedad relacionada en blanco
                $retvalItem->{$relationData->propName} = $relationData->query->isSingle ? null : [];

                $localPropName = $relationData->local;
                if (!isset($record->$localPropName)) {
                    continue;
                }

                $localValues = is_array($record->$localPropName) ? $record->$localPropName : [$record->$localPropName];
                foreach ($localValues as $hashValue) {
                    if (!is_scalar($hashValue)) {
                        continue;
                    }

                    $relationData->hash[$hashValue][] = $retvalItem;
                }
            }

            $retval[] = $retvalItem;
        }

        // Resolve each relation
        foreach ($relations as $relationData) {
            // Fetch all related records
            if (count($relationData->hash)) {
                $relatedRecords = $this->query($relationData->query, (object)[
                    "keyName" => $relationData->foreign,
                    "keyValue" => array_keys($relationData->hash)
                ]);
            } else {
                $relatedRecords = [];
            }

            // Merge related records into result
            foreach ($relatedRecords as $relRecord) {
                if (!isset($relRecord->{$relationData->foreign})) {
                    continue;
                }

                $keyValue = $relRecord->{$relationData->foreign};

                $arrKeyValues = is_array($keyValue) ? $keyValue : [$keyValue];
                foreach ($arrKeyValues as $keyValue) {
                    if (!isset($relationData->hash[$keyValue])) {
                        continue;
                    }

                    $parentRecords = $relationData->hash[$keyValue];
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
        }

        return $retval;
    }
}
