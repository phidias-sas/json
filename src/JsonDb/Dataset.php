<?php

namespace Phidias\JsonDb;

class Dataset
{
    private $databases;
    private $maxLimit;

    public function __construct()
    {
        $this->databases = [];
        $this->maxLimit = 5000;
    }

    public function addDatabase($dbName, $dbObject, $isDefault = false)
    {
        if (!is_a($dbObject, 'Phidias\JsonDb\Database')) {
            throw new \Exception("Database must be of type Phidias\JsonDb\Database");
        }

        $this->databases[$dbName] = $dbObject;
        if ($isDefault) {
            $this->databases['default'] = $this->databases[$dbName];
        }
    }

    public function query($query, $joinData = null)
    {
        $retval = [];

        $query = Select::factory($query);

        if (!isset($this->databases[$query->from->db])) {
            throw new \Exception("Database '{$query->from->db}' not found in dataset");
        }

        $db = $this->databases[$query->from->db];
        $table = $db->getTable($query->from->table);

        // Establecer propiedades a seleccionar e identificar relaciones
        $useAllProperties = false;
        $properties = [];
        $relations = [];

        foreach ($query->properties as $property) {
            if (is_object($property)) {
                $propName = array_keys(get_object_vars($property))[0];
                $propSource = $property->$propName;

                if (!isset($propSource->on)) {
                    throw new \Exception("No 'on' specified in nested source '$propName'");
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
                $table->attribute($property);
                $properties[] = $property;

                if ($property == "*") {
                    $useAllProperties = true;
                }
            }
        }

        // Establecer condiciones de "match"
        foreach ($query->match as $keyName => $keyValue) {
            $table->match($keyName, $keyValue);
        }

        // Establecer condicionales ("where")
        if ($query->where) {
            $table->where($query->where);
        }

        // Limite
        $limit = $this->maxLimit;
        if (isset($query->limit)) {
            $limit = max(0, min($limit, $query->limit));
        }
        $table->limit($limit);

        // Forzar limit 1  cuando es un anidado "single"
        if ($query->isSingle) {
            $table->limit(1);
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
                $localPropName = $relationData->local;
                $hashValue = $record->$localPropName;
                $relationData->hash[$hashValue][] = $retvalItem;

                // Inicializar la propiedad relacionada en blanco
                $retvalItem->{$relationData->propName} = $relationData->query->isSingle ? null : [];
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

        return $retval;
    }
}
