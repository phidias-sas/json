<?php

namespace Phidias\JsonDb;

class Table
{
    public function insert($data)
    {
        return null;
    }

    public function update($recordId, $data)
    {
        return null;
    }

    public function delete($recordId)
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

    public function having($having)
    {
        return $this;
    }

    public function groupBy($groupBy)
    {
        return $this;
    }

    public function fetch()
    {
        return [];
    }


    public function sql($query, $params = null)
    {
        return $this;
    }
}
