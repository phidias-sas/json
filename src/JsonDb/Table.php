<?php

namespace Phidias\JsonDb;

class Table
{
    private $tableName;
    private $attributes;
    private $collection;

    private $useAllAttributes;
    private $indexableProperties;

    public function __construct($tableName, $indexableProperties = [])
    {
        $this->tableName = $tableName;
        $this->indexableProperties = $indexableProperties;

        $this->attributes = [];
        $this->useAllAttributes = false;

        $this->collection = Record::collection()
            ->attribute("id")
            ->match("tableId", $this->tableName);
    }

    public function insert($data)
    {
        return Orm::postRecord($this->tableName, $data, $this->indexableProperties);
    }

    public function where($condition)
    {
    }

    public function attribute($attributeName)
    {
        $attributeName = trim($attributeName);
        if (!$attributeName) {
            throw new \Exception("Attribute name cannot be empty");
        }

        $this->attributes[$attributeName] = $attributeName;

        if (substr($attributeName, 0, 7) == "record.") {
            $this->collection->attribute(substr($attributeName, 7));
        } else if ($attributeName == "*") {
            $this->useAllAttributes = true;
            $this->collection->attribute("data");
        } else {
            $this->collection->attribute("x.$attributeName", "JSON_EXTRACT(data, '$.$attributeName')");
        }

        return $this;
    }

    public function match($attributeName, $attributeValue)
    {
        if (substr($attributeName, 0, 7) == "record.") {
            $this->collection->match(substr($attributeName, 7), $attributeValue);
        } else {
            Indexes::filterCollection($this->collection, $this->tableName, $attributeName, $attributeValue);
        }

        return $this;
    }

    public function limit($limit)
    {
        $this->collection->limit($limit);
        return $this;
    }

    public function fetch()
    {
        $retval = [];

        foreach ($this->collection->find()->fetchAll() as $record) {
            if ($this->useAllAttributes) {
                $retvalItem = isset($record->data) && is_object($record->data) ? $record->data : new \stdClass;
            } else {
                $retvalItem = new \stdClass;
                $retvalItem->id = $record->id; // PLOP id (initial id)

                foreach ($this->attributes as $attributeName) {
                    if (substr($attributeName, 0, 7) == "record.") {
                        $attrName = substr($attributeName, 7);
                        $retvalItem->$attributeName = isset($record->$attrName) ? $record->$attrName : null;
                    } else if (isset($record->{"x." . $attributeName})) {
                        $retvalItem->$attributeName = json_decode($record->{"x." . $attributeName});
                        unset($record->{"x." . $attributeName});
                    } else {
                        // Se habia solicitado el atributo mediante this->attribute
                        // pero en ningun lado de los resultados se puede encontrar

                        // $retvalItem->$attributeName = null;
                    }
                }
            }

            // PLOP id
            if (!isset($retvalItem->id)) {
                $retvalItem->id = $record->id;
            }

            $retval[] = $retvalItem;
        }

        return $retval;
    }
}
