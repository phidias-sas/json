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
        if (!is_a($dbObject, 'Phidias\JsonDb\DatabaseInterface')) {
            throw new \Exception("Database must implement Phidias\JsonDb\DatabaseInterface");
        }

        $this->databases[$dbName] = $dbObject;
        if ($isDefault) {
            $this->databases['default'] = $this->databases[$dbName];
        }
    }

    public function query($query, $joinData = null)
    {
        // Convertir arreglos de PHP a objetos
        $query = json_decode(json_encode($query));

        $retval = [];

        /*
        Determinar fuentes de datos (nombre de db y nombre de tabla):
        "from": "tableName",   // se asume dbName = default
        o:
        "from": {"dbName": "tableName"}
        */
        if (!isset($query->from)) {
            throw new \Exception("No source specified");
        }

        if (is_string($query->from)) {
            $dbName = "default";
            $tableName = trim($query->from);
        } else if (is_object($query->from)) {
            $dbName = array_keys(get_object_vars($query->from))[0];
            $tableName = trim($query->from->$dbName);
        }

        if (!isset($this->databases[$dbName])) {
            throw new \Exception("Database '$dbName' not found in dataset");
        }

        if (!$tableName) {
            throw new \Exception("No table specified");
        }

        $db = $this->databases[$dbName];
        $table = $db->getTable($tableName);

        // Establecer propiedades a seleccionar e identificar relaciones
        $incomingProperties = isset($query->properties) && is_array($query->properties) ? $query->properties : ['*'];

        $useAllProperties = false;
        $properties = [];
        $relations = [];

        foreach ($incomingProperties as $property) {
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
        if (isset($query->match)) {
            foreach ($query->match as $keyName => $keyValue) {
                $table->match($keyName, $keyValue);
            }
        }

        if ($joinData) {
            $table->match($joinData->keyName, $joinData->keyValue);

            $table->attribute($joinData->keyName);
            $properties[] = $joinData->keyName;
        }

        // Limite
        $limit = $this->maxLimit;
        if (isset($query->limit)) {
            $limit = max(0, min($limit, $query->limit));
        }
        $table->limit($limit);

        // Fetch all records and populate relation condition data
        foreach ($table->fetch() as $record) {
            if ($useAllProperties) {
                $retvalItem = $record;
            } else {
                $retvalItem = new \stdClass;
                $retvalItem->id = isset($record->id) ? $record->id : null;

                foreach ($properties as $propName) {
                    $retvalItem->$propName = isset($record->$propName) ? $record->$propName : null;
                }
            }

            foreach ($relations as $relationData) {
                $localPropName = $relationData->local;
                $hashValue = $record->$localPropName;
                $relationData->hash[$hashValue][] = $retvalItem;

                $retvalItem->{$relationData->propName} = [];
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
                    if (!isset($parentRecord->{$relationData->propName}) || !is_array($parentRecord->{$relationData->propName})) {
                        $parentRecord->{$relationData->propName} = [];
                    }

                    $parentRecord->{$relationData->propName}[] = $relRecord;
                }
            }
        }

        return $retval;
    }
}
