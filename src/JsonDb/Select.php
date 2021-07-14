<?php

namespace Phidias\JsonDb;

class Select
{
    public $from;
    public $on;
    public $search;
    public $properties;
    public $match;
    public $where;
    public $limit;
    public $page;
    public $order;
    public $having;
    public $groupBy;
    public $isSingle;

    public $sql;

    public static function factory($arrayOrObject)
    {
        if (is_a($arrayOrObject, 'Phidias\JsonDb\Select')) {
            return $arrayOrObject;
        }

        $incoming = json_decode(json_encode($arrayOrObject));
        if (!isset($incoming->from)) {
            throw new \Exception("Could not determine 'from'");
        }

        if (is_object($incoming->from)) {
            $dbName = array_keys(get_object_vars($incoming->from))[0];
            $tableName = $incoming->from->$dbName;

            $retval = new Select($dbName, $tableName);
        } else {
            $retval = new Select($incoming->from);
        }

        if (@is_object($incoming->on)) {
            $localProperty = array_keys(get_object_vars($incoming->on))[0];
            $parentProperty = $incoming->on->$localProperty;
            $retval->on($localProperty, $parentProperty);
        }

        if (!isset($incoming->properties)) {
            $incoming->properties = [];
        }
        if (is_string($incoming->properties)) {
            $incoming->properties = [$incoming->properties];
        }

        foreach ($incoming->properties as $incomingProperty) {
            if (is_object($incomingProperty)) {
                $propertyName = array_keys(get_object_vars($incomingProperty))[0];
                $propertySource = $incomingProperty->$propertyName;

                if (is_object($propertySource)) {
                    $nestedSelect = self::factory($propertySource);
                    $retval->property($propertyName, $nestedSelect);
                } else {
                    $retval->property($propertyName, $propertySource);
                }
            } else {
                $retval->property($incomingProperty);
            }
        }

        if (@is_object($incoming->match)) {
            foreach ($incoming->match as $propertyName => $targetValue) {
                $retval->match($propertyName, $targetValue);
            }
        }

        if (isset($incoming->where)) {
            $retval->where($incoming->where);
        }

        if (isset($incoming->limit)) {
            $retval->limit($incoming->limit);
        }

        if (isset($incoming->page)) {
            $retval->page($incoming->page);
        }

        if (isset($incoming->order)) {
            $retval->order($incoming->order);
        }

        if (isset($incoming->having)) {
            $retval->having($incoming->having);
        }

        if (isset($incoming->groupBy)) {
            $retval->groupBy($incoming->groupBy);
        }

        if (isset($incoming->search)) {
            $retval->search($incoming->search);
        }

        if (isset($incoming->single) && $incoming->single) {
            $retval->single();
        }

        return $retval;
    }

    public static function from($from, $secondArgument = null)
    {
        return new Select($from, $secondArgument);
    }

    public function __construct($from, $secondArgument = null)
    {
        $this->from = new \stdClass;

        if ($secondArgument) {
            $this->from->db = $from;
            $this->from->table = $secondArgument;
        } else {
            $this->from->db = 'default';
            $this->from->table = $from;
        }

        $this->on = new \stdClass;
        $this->properties = [];
        $this->match = new \stdClass;
        $this->where = null;
        $this->limit = null;
        $this->page = null;
        $this->order = null;
        $this->having = null;
        $this->groupBy = null;
        $this->isSingle = false;

        $this->sql = null;
    }

    public function on($localProperty, $parentProperty)
    {
        $this->on->$localProperty = $parentProperty;

        return $this;
    }

    public function prop($propertyName, $propertySource = null)
    {
        if (is_array($propertyName)) {
            foreach ($propertyName as $keyName => $item) {
                if (is_numeric($keyName)) {
                    $this->prop($item);
                } else {
                    $this->prop($keyName, $item);
                }
            }
            return $this;
        }

        if (is_object($propertyName)) {
            $prop = array_keys(get_object_vars($propertyName))[0];
            $propertySource = $propertyName->$prop;
            $this->property($propertyName, $propertySource);
            return $this;
        }

        if ($propertySource) {
            $nested = new \stdClass;
            $nested->$propertyName = $propertySource;
            $this->properties[] = $nested;
        } else {
            $this->properties[] = $propertyName;
        }

        return $this;
    }

    // public function properties(...$arguments)
    public function properties()
    {
        $arguments = func_get_args(); // en vez de ...$arguments para soportar php 5.3 (!!!)
        foreach ($arguments as $prop) {
            if (is_object($prop)) {
                $propertyName = array_keys(get_object_vars($prop))[0];
                $propertySource = $prop->$propertyName;
                $this->property($propertyName, $propertySource);
            } else {
                $this->property($prop);
            }
        }

        return $this;
    }

    public function property($propertyName, $propertySource = null)
    {
        if ($propertySource) {
            $nested = new \stdClass;
            $nested->$propertyName = $propertySource;

            $this->properties[] = $nested;
        } else {
            $this->properties[] = $propertyName;
        }

        return $this;
    }

    public function match($propertyName, $targetValue)
    {
        $this->match->$propertyName = $targetValue;

        return $this;
    }

    public function where($condition, $params = null)
    {
        $this->where = Utils::bindParameters($condition, $params);

        return $this;
    }

    public function limit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    public function page($page)
    {
        $this->page = $page;

        return $this;
    }

    public function order($order)
    {
        $this->order = $order;

        return $this;
    }

    public function having($having)
    {
        $this->having = $having;

        return $this;
    }

    public function groupBy($groupBy)
    {
        $this->groupBy = $groupBy;

        return $this;
    }

    public function single()
    {
        $this->isSingle = true;

        return $this;
    }

    public function search($searchString)
    {
        $this->search = $searchString;

        return $this;
    }

    public function sql($sql, $params = null)
    {
        $this->sql = (object)[
            "query" => $sql,
            "params" => $params
        ];

        return $this;
    }
}
