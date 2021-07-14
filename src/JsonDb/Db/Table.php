<?php

namespace Phidias\JsonDb\Db;

class Table extends \Phidias\JsonDb\Table
{
    private $entityName;
    private $collection;
    private $attributes;

    public function __construct($entityName)
    {
        if (!class_exists($entityName)) {
            throw new \Exception("Entity '$entityName' not found");
        }

        $this->entityName = $entityName;
        $this->collection = $entityName::collection();
        $this->attributes = [];
    }

    public function attribute($attributeName)
    {
        if ($attributeName == "*") {
            $this->collection->allAttributes();
            foreach (array_keys($this->collection->getAttributes()) as $attrName) {
                $this->attributes[$attrName] = $attrName;
            }
        } else {
            $this->attributes[$attributeName] = $attributeName;
            $this->collection->attribute($attributeName);
        }

        return $this;
    }

    public function match($propertyName, $propertyValue)
    {
        if (is_array($propertyValue) && !count($propertyValue)) {
            $this->collection->where(0);
            return $this;
        }

        $this->collection->match($propertyName, $propertyValue);
        return $this;
    }

    public function limit($limit)
    {
        $this->collection->limit($limit);
        return $this;
    }

    public function page($page)
    {
        $this->collection->page($page);
        return $this;
    }

    public function order($order)
    {
        $this->collection->order($order);
        return $this;
    }

    public function having($having)
    {
        $this->collection->having($having);
        return $this;
    }

    public function groupBy($groupBy)
    {
        $this->collection->groupBy($groupBy);
        return $this;
    }

    public function where($condition)
    {
        if (is_string($condition)) {
            $this->collection->where($condition);
            return $this;
        }

        $conditionSql = $this->evaluateWhere($condition);
        $this->collection->where($conditionSql);

        return $this;
    }

    public function fetch()
    {
        $retval = [];

        foreach ($this->collection->find()->fetchAll() as $record) {
            $retvalItem = new \stdClass;
            foreach ($this->attributes as $attributeName) {
                $retvalItem->$attributeName = $record->$attributeName;
            }

            $retval[] = $retvalItem;
        }

        return $retval;
    }

    public function sql($query, $params = null)
    {
        $this->collection->query($query, $params);
        return $this;
    }
}
