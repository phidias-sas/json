<?php

namespace Phidias\JsonDb;

class Select
{
    public $from;
    public $on;
    public $properties;
    public $match;
    public $where;
    public $limit;
    public $order;
    public $isSingle;

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

        if (@!is_array($incoming->properties)) {
            $incoming->properties = [];
        }
        foreach ($incoming->properties as $incomingProperty) {
            if (is_object($incomingProperty)) {
                $propertyName = array_keys(get_object_vars($incomingProperty))[0];
                $nestedSelect = self::factory($incomingProperty->$propertyName);
                $retval->property($propertyName, $nestedSelect);
            } else {
                $retval->property($incomingProperty);
            }
        }

        if (@is_object($incoming->match)) {
            $propertyName = array_keys(get_object_vars($incoming->match))[0];
            $targetValue = $incoming->match->$propertyName;
            $retval->match($propertyName, $targetValue);
        }

        if (isset($incoming->where)) {
            $retval->where($incoming->where);
        }

        if (isset($incoming->limit)) {
            $retval->limit($incoming->limit);
        }

        if (isset($incoming->order)) {
            $retval->order($incoming->order);
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
        $this->order = null;
        $this->isSingle = false;
    }

    public function on($localProperty, $parentProperty)
    {
        $this->on->$localProperty = $parentProperty;

        return $this;
    }

    public function properties(...$arguments)
    {
        // $arguments = func_get_args();
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

    public function where($condition)
    {
        $this->where = $condition;

        return $this;
    }

    public function limit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    public function order($order)
    {
        $this->order = $order;

        return $this;
    }

    public function single()
    {
        $this->isSingle = true;

        return $this;
    }
}
