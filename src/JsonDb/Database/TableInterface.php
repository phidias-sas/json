<?php

namespace Phidias\JsonDb\Database;

interface TableInterface
{
    public function __construct($tableId);
    public function attribute($attributeName);
    public function match($attributeName, $attributeValue);
    public function where($condition);
    public function limit($limit);
    public function fetch();
}