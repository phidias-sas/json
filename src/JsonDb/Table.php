<?php

namespace Phidias\JsonDb;

class Table
{
    private $vm;

    public function setVm($vm)
    {
        $this->vm = new SqlVm;
        $this->vm->operators = $vm->operators;
        $this->vm->setTranslationFunction([$this, 'translateFieldName']);
    }

    public function translateFieldName($fieldName)
    {
        return $fieldName;
    }

    public function evaluateWhere($condition)
    {
        return $this->vm->evaluate($condition);
    }

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

    public function attribute($attributeName, $attributeSource = null)
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

    public function page($page)
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

    public function search($searchString)
    {
        return $this;
    }

    public function sql($query, $params = null)
    {
        return $this;
    }
}
