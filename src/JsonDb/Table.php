<?php

namespace Phidias\JsonDb;

class Table
{
    public function insert($data)
    {
        return null;
    }

    public function where($condition)
    {
        return $this;
    }

    public function attribute($attributeName)
    {
        return $this;
    }

    public function match($attributeName, $attributeValue)
    {
        return $this;
    }

    public function limit($limit)
    {
        return $this;
    }

    public function order($order)
    {
        return $this;
    }

    public function fetch()
    {
        return [];
    }
}
